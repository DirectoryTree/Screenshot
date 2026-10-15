<?php

namespace DirectoryTree\Screenshot\Contracts;

use DirectoryTree\Screenshot\Screenshot;

/**
 * @mixin Screenshot
 */
interface Factory
{
    /**
     * Get a screenshot factory for the given driver.
     */
    public function driver($driver = null): Screenshot;
}
