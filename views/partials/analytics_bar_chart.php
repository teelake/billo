<?php
declare(strict_types=1);

/** @var string $chart_title */
/** @var list<string> $chart_labels */
/** @var list<float|int> $chart_values */
/** @var string $chart_format int|money */
/** @var string $chart_accent slate|emerald|amber */

$chart_title = $chart_title ?? '';
$chart_labels = isset($chart_labels) && is_array($chart_labels) ? $chart_labels : [];
$chart_values = isset($chart_values) && is_array($chart_values) ? $chart_values : [];
$chart_format = $chart_format ?? 'int';
$chart_accent = $chart_accent ?? 'slate';

$max = 0.0;
foreach ($chart_values as $v) {
    $max = max($max, (float) $v);
}
if ($max <= 0) {
    $max = 1.0;
}

$fmt = static function (string $format, float|int $v): string {
    if ($format === 'money') {
        return number_format((float) $v, 0, '.', ',');
    }

    return (string) (int) round((float) $v);
};
?>
<div class="analytic-chart analytic-chart--accent-<?= billo_e($chart_accent) ?>">
    <h3 class="analytic-chart__title"><?= billo_e($chart_title) ?></h3>
    <div class="analytic-chart__bars" role="img" aria-label="<?= billo_e($chart_title) ?>">
        <?php foreach ($chart_labels as $i => $lab): ?>
            <?php
            $v = $chart_values[$i] ?? 0;
            $fv = (float) $v;
            $pct = round(100 * $fv / $max, 2);
            $pct = min(100.0, max(0.0, $pct));
            ?>
            <div class="analytic-chart__row">
                <span class="analytic-chart__lab"><?= billo_e($lab) ?></span>
                <div class="analytic-chart__track">
                    <div class="analytic-chart__fill" style="width: <?= billo_e((string) $pct) ?>%;"></div>
                </div>
                <span class="analytic-chart__val"><?= billo_e($fmt($chart_format, $fv)) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</div>
