<?php

namespace App\Http\Resources;

class DependencyCollection extends ScaListCollection
{
    /**
     * @var string
     */
    public $collects = DependencyResource::class;
}
