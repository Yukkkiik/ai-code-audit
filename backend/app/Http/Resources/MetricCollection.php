<?php

namespace App\Http\Resources;

class MetricCollection extends SastListCollection
{
    /**
     * @var string
     */
    public $collects = MetricResource::class;
}
