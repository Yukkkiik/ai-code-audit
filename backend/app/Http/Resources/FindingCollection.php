<?php

namespace App\Http\Resources;

class FindingCollection extends SastListCollection
{
    /**
     * @var string
     */
    public $collects = FindingResource::class;
}
