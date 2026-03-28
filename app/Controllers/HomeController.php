<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\View;
use App\Repositories\LandingPageRepository;
use App\Repositories\PlanItemRepository;
use App\Repositories\PlanRepository;

final class HomeController extends Controller
{
    public function __construct(
        private PlanRepository $plans = new PlanRepository(),
        private PlanItemRepository $planItems = new PlanItemRepository(),
        private LandingPageRepository $landingBlocks = new LandingPageRepository(),
    ) {
    }

    public function index(): void
    {
        $planRows = $this->plans->listActiveForDisplay();
        $ids = array_values(array_filter(array_map(static fn (array $p): int => (int) ($p['id'] ?? 0), $planRows), static fn (int $i) => $i > 0));
        $featuresByPlan = $ids !== [] ? $this->planItems->listGroupedForPlans($ids) : [];

        View::render('home/index', [
            'landing_plans' => $planRows,
            'plan_features' => $featuresByPlan,
            'landing_faqs' => $this->landingBlocks->listFaqsPublic(),
            'landing_logos' => $this->landingBlocks->listTrustedLogosPublic(),
            'landing_testimonials' => $this->landingBlocks->listTestimonialsPublic(),
        ]);
    }
}
