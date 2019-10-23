<?php

/**
 * Created by PhpStorm.
 * User: mathiasschreiber
 * Date: 22.01.18
 * Time: 14:12
 */

namespace App\Viewhelpers;

use Symfony\Component\Routing\Generator\UrlGenerator;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractTagBasedViewHelper;

class LinkViewHelper extends AbstractTagBasedViewHelper
{
    /**
     * @var string
     */
    protected $tagName = 'a';

    public function initializeArguments(): void
    {
        $this->registerArgument('route', 'string', 'The route identifier to link to', true);
        $this->registerArgument('arguments', 'array', 'Arguments at add to the link', false);
    }

    /**
     * @return string
     */
    public function render(): string
    {
        /** @var UrlGenerator $urlGenerator */
        $urlGenerator = $this->renderingContext->getContainer()->get('router');
        $route = $this->arguments['route'];
        $arguments = $this->arguments['arguments'];
        $uri = $urlGenerator->generate($route, $arguments);
        if ($uri !== '') {
            $this->tag->addAttribute('href', $uri);
            $this->tag->setContent($this->renderChildren());
            $result = $this->tag->render();
        } else {
            $result = $this->renderChildren();
        }
        return $result;
    }
}
