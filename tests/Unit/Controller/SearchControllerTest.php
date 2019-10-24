<?php

namespace App\Tests\Unit\Controller;

use App\Controller\SearchController;
use App\Repository\ElasticRepository;
use App\Service\FluidRenderingContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperResolver;
use TYPO3Fluid\Fluid\View\TemplatePaths;
use TYPO3Fluid\Fluid\View\TemplateView;

class SearchControllerTest extends TestCase
{
    /**
     * @test
     */
    public function indexActionRendersIndexTemplate()
    {
        $subject = new SearchController();
        $view = $this->getMockedView();
        $this->setProperty($subject, 'view', $view);

        $view->expects($this->once())->method('render')->with('index');

        $subject->indexAction();
    }

    /**
     * @test
     */
    public function searchActionAssignsQueryToTemplate()
    {
        $subject = new SearchController();
        $view = $this->getMockedView();
        $this->setProperty($subject, 'view', $view);

        $request = $this->getMockBuilder(Request::class)->getMock();
        $request->query = $this->getMockBuilder(ParameterBag::class)->getMock();
        $request->query->expects($this->any())->method('get')->with('q')->willReturn('searchTerm');

        $view->expects($this->once())->method('assignMultiple')->with($this->callback(function (array $variables) {
            return isset($variables['q'])
                && $variables['q'] === 'searchTerm'
                ;
        }));

        $subject->searchAction($request);
    }

    /**
     * @test
     */
    public function searchActionAssignsResultsToTemplate()
    {
        $this->markTestIncomplete('Need to move repository to DI and replace by mock');

        $subject = new SearchController();
        $view = $this->getMockedView();
        $this->setProperty($subject, 'view', $view);

        $request = $this->getMockBuilder(Request::class)->getMock();
        $request->query = $this->getMockBuilder(ParameterBag::class)->getMock();
        $request->query->expects($this->any())->method('get')->with('q')->willReturn('searchTerm');

        $repository = $this->getMockBuilder(ElasticRepository::class)->disableOriginalConstructor()->getMock();
        $repository->expects($this->once())->method('findByQuery')->with('searchTerm')->willReturn([
            'resultItem1' => 'something',
            'resultItem2' => 'something',
        ]);

        $view->expects($this->once())->method('assignMultiple')->with($this->callback(function (array $variables) {
            return isset($variables['results'])
                && $variables['results'] === [
                    'resultItem1' => 'something',
                    'resultItem2' => 'something',
                ]
                ;
        }));

        $subject->searchAction($request);
    }

    /**
     * @test
     */
    public function searchActionRendersSearchTemplate()
    {
        $subject = new SearchController();
        $view = $this->getMockedView();
        $this->setProperty($subject, 'view', $view);

        $request = $this->getMockBuilder(Request::class)->getMock();
        $request->query = $this->getMockBuilder(ParameterBag::class)->getMock();
        $request->query->expects($this->any())->method('get')->with('q')->willReturn('searchTerm');


        $view->expects($this->once())->method('render')->with('search');

        $subject->searchAction($request);
    }

    private function getMockedView()
    {
        $view = $this->getMockBuilder(TemplateView::class)->getMock();

        $renderingMock = $this->getMockBuilder(FluidRenderingContext::class)->disableOriginalConstructor()->getMock();
        $renderingMock->expects($this->any())->method('getTemplatePaths')->willReturn(
            $this->getMockBuilder(TemplatePaths::class)->disableOriginalConstructor()->getMock()
        );
        $view->expects($this->any())->method('getRenderingContext')->willReturn($renderingMock);

        $view->expects($this->any())->method('getViewHelperResolver')->willReturn(
            $this->getMockBuilder(ViewHelperResolver::class)->disableOriginalConstructor()->getMock()
        );

        return $view;
    }

    private function setProperty($instance, string $property, $value)
    {
        $propertyReflection = new \ReflectionProperty($instance, $property);
        $propertyReflection->setAccessible(true);
        $propertyReflection->setValue($instance, $value);
    }
}
