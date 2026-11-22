<?php

namespace App\Services\Sast;

/**
 * Ringkasan metrik seluruh audit (murni). `averageMaintainabilityIndex` dibobot SLOC dan hanya memakai file yang
 * MI-nya dapat dihitung; null bila tidak ada.
 */
final readonly class MetricsAggregate
{
    public function __construct(
        public int $fileCount,
        public int $functionCount,
        public int $loc,
        public int $sloc,
        public ?float $averageCyclomaticComplexity,
        public int $maxCyclomaticComplexity,
        public ?float $averageMaintainabilityIndex,
    ) {}

    /**
     * @param  list<FileMetrics>  $files
     */
    public static function from(array $files): self
    {
        $functionCount = 0;
        $ccSum = 0;
        $ccMax = 0;
        $loc = 0;
        $sloc = 0;
        $miWeighted = 0.0;
        $miWeight = 0;

        foreach ($files as $file) {
            $loc += $file->values->loc;
            $sloc += $file->values->sloc;

            foreach ($file->functions as $function) {
                $functionCount++;
                $ccSum += $function->values->cyclomaticComplexity;
                $ccMax = max($ccMax, $function->values->cyclomaticComplexity);
            }

            $mi = $file->values->maintainabilityIndex();

            if ($mi !== null) {
                $miWeighted += $mi * $file->values->sloc;
                $miWeight += $file->values->sloc;
            }
        }

        return new self(
            count($files),
            $functionCount,
            $loc,
            $sloc,
            $functionCount === 0 ? null : $ccSum / $functionCount,
            $ccMax,
            $miWeight === 0 ? null : $miWeighted / $miWeight,
        );
    }
}
