<?php

/**
 * Created by PhpStorm.
 * User: mathiasschreiber
 * Date: 03.02.18
 * Time: 13:05
 */

namespace App\Service;

use TYPO3Fluid\Fluid\Core\Rendering\RenderingContext;

class FluidRenderingContext extends RenderingContext
{
    protected $container;

    /**
     * @return mixed
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * @param mixed $container
     */
    public function setContainer($container): void
    {
        $this->container = $container;
    }
}
