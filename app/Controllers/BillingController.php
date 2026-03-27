<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Repositories\OrganizationSubscriptionRepository;
use App\Repositories\PlanItemRepository;
use App\Repositories\PlanRepository;
use App\Repositories\SubscriptionOrderRepository;
use App\Services\Payments\PaymentGatewayFactory;
use App\Services\Payments\PaystackGateway;

final class BillingController extends Controller
{
    public function __construct(
        private Request $request,
        private PlanRepository $plans = new PlanRepository(),
        private OrganizationSubscriptionRepository $subscriptions = new OrganizationSubscriptionRepository(),
        private SubscriptionOrderRepository $orders = new SubscriptionOrderRepository(),
    ) {
    }

    public function index(): void
    {
        $ctx = $this->requireAuthRole(['owner', 'admin']);
        $this->subscriptions->ensureFreePlan($ctx['organization_id']);
        $plans = $this->plans->listActiveForDisplay();
        $current = $this->subscriptions->findWithPlan($ctx['organization_id']);
        $planIds = [];
        foreach ($plans as $p) {
            if (!is_array($p)) {
                continue;
            }
            $pid = (int) ($p['id'] ?? 0);
            if ($pid > 0) {
                $planIds[] = $pid;
            }
        }
        $planItemsByPlan = (new PlanItemRepository())->listGroupedForPlans($planIds);

        View::render('billing/index', [
            'plans' => $plans,
            'plan_items_by_plan' => $planItemsByPlan,
            'current' => $current,
            'user_name' => (string) Session::get('user_name', ''),
            'role' => $ctx['role'],
            'show_team_nav' => true,
            'error' => Session::flash('error') ?? '',
            'success' => Session::flash('success') ?? '',
        ]);
    }

    public function subscribe(): void
    {
        $ctx = $this->requireAuthRole(['owner', 'admin']);
        if (!Csrf::validate($this->request->input('_csrf'))) {
            Session::flash('error', 'Invalid session. Please try again.');
            $this->redirect('/billing');
        }

        $planId = (int) $this->request->input('plan_id', 0);
        if ($planId <= 0) {
            Session::flash('error', 'Select a plan.');
            $this->redirect('/billing');
        }

        $plan = $this->plans->findActiveById($planId);
        if ($plan === null) {
            Session::flash('error', 'That plan is not available.');
            $this->redirect('/billing');
        }

        $orgId = $ctx['organization_id'];
        $price = (float) ($plan['price_amount'] ?? 0);
        $currency = (string) ($plan['currency'] ?? 'NGN');

        if ($price < 0.01) {
            try {
                $this->subscriptions->setPlan($orgId, $planId, 'active');
            } catch (\Throwable) {
                Session::flash('error', 'Could not update subscription. Run database migrations (015) and try again.');
                $this->redirect('/billing');
            }
            Session::flash('success', 'Your workspace is now on the ' . (string) ($plan['name'] ?? 'selected') . ' plan.');
            $this->redirect('/billing');
        }

        $gateway = PaymentGatewayFactory::active();
        if (!$gateway instanceof PaystackGateway || !$gateway->isConfigured()) {
            Session::flash(
                'error',
                'Paid plans require Paystack (payments.provider = paystack with secret key). Ask a platform operator to configure payments, or choose a free plan.'
            );
            $this->redirect('/billing');
        }

        $email = strtolower(trim((string) Session::get('user_email', '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Your profile needs a valid email to start checkout.');
            $this->redirect('/account/profile');
        }

        try {
            $orderId = $this->orders->createPending($orgId, $planId, $price, $currency);
            $session = $gateway->beginSubscriptionCheckout($orderId, $orgId, $planId, $price, $currency, $email);
            $this->orders->setCheckoutRef($orderId, $orgId, $session['checkout_ref']);
        } catch (\Throwable $e) {
            error_log('Billing subscribe: ' . $e->getMessage());
            Session::flash('error', 'Could not start payment. Check Paystack configuration and try again.');
            $this->redirect('/billing');
        }

        header('Location: ' . $session['redirect_url'], true, 303);
        exit;
    }
}
