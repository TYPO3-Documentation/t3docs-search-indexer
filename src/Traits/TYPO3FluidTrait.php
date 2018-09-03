<?php
declare(strict_types=1);
namespace App\Traits;

use App\Service\FluidRenderingContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use TYPO3Fluid\Fluid\Core\Cache\SimpleFileCache;
use TYPO3Fluid\Fluid\View\TemplatePaths;
use TYPO3Fluid\Fluid\View\TemplateView;

trait TYPO3FluidTrait
{
    /**
     * @var TemplateView
     */
    protected $view;

    public function __construct()
    {
        $wtf = new TemplateView();
        $fluidRenderingContext = new FluidRenderingContext($wtf);
        $this->view = new TemplateView($fluidRenderingContext);
    }

    protected function initializeView(): TemplateView
    {
        $this->view->getRenderingContext()->setContainer($this->container);
        $this->view->setCache(new SimpleFileCache('../var/cache/fluid/'));
        $this->view->getRenderingContext()->setControllerName(substr(static::class, strrpos(static::class, '\\') + 1, -10));
        $this->view->getRenderingContext()->getTemplatePaths()->fillFromConfigurationArray([
            TemplatePaths::CONFIG_TEMPLATEROOTPATHS => ['../templates/Private/Templates/'],
            TemplatePaths::CONFIG_PARTIALROOTPATHS => ['../templates/Private/Partials/'],
            TemplatePaths::CONFIG_LAYOUTROOTPATHS => ['../templates/Private/Layouts/'],
        ]);
        $this->view->getViewHelperResolver()->addNamespace('fs', 'App\\Viewhelpers');
        return $this->view;
    }

    /**
     * @param string $view
     * @param array $parameters
     * @param null|Response $response
     * @return Response
     * @throws \UnexpectedValueException
     */
    protected function render(string $view, array $parameters = array(), ?Response $response = null): Response
    {
        $this->view->assignMultiple($parameters);
        $response = $response ?? (new Response())->prepare(Request::createFromGlobals());
        return $response->setContent($this->initializeView()->render($view));
    }
}