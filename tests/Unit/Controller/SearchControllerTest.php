<?php

namespace App\Tests\Unit\Controller;

use App\Controller\SearchController;
use App\Repository\ElasticRepository;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;

class SearchControllerTest extends TestCase
{
    use ProphecyTrait;

    private ObjectProphecy $view;

    private ObjectProphecy $container;

    public function setUp(): void
    {
        $this->view = $this->prophesize(Environment::class);

        $this->container = $this->prophesize(ContainerInterface::class);
        $this->container->has('templating')->willReturn(false);
        $this->container->has('twig')->willReturn(true);
        $this->container->get('twig')->willReturn($this->view->reveal());
    }

    /**
     * @test
     */
    public function indexActionRendersIndexTemplate(): void
    {
        $subject = new SearchController();
        $this->setProperty($subject, 'container', $this->container->reveal());

        $this->view->render('search/index.html.twig', [])->shouldBeCalledTimes(1);

        $subject->index();
    }

    /**
     * @test
     */
    public function searchActionAssignsQueryToTemplate(): void
    {
        $subject = new SearchController();
        $this->setProperty($subject, 'container', $this->container->reveal());

        $query = new ParameterBag(['q' => 'searchTerm', 'page' => '1']);

        $request = $this->prophesize(Request::class);
        $request->query = $query;

        $this->view->render(Argument::any(), Argument::that(fn (array $variables) => isset($variables['q'])
            && $variables['q'] === 'searchTerm'))->shouldBeCalledTimes(1);

        $subject->search($request->reveal());
    }

    /**
     * @test
     */
    public function searchActionAssignsResultsToTemplate(): never
    {
        self::markTestIncomplete('Need to move repository to DI and replace by mock');

        $subject = new SearchController();
        $view = $this->getMockedView();
        $this->setProperty($subject, 'view', $view);

        $request = $this->getMockBuilder(Request::class)->getMock();
        $request->query = $this->getMockBuilder(ParameterBag::class)->getMock();
        $request->query->expects(self::any())->method('get')->with('q')->willReturn('searchTerm');

        $repository = $this->getMockBuilder(ElasticRepository::class)->disableOriginalConstructor()->getMock();
        $repository->expects(self::once())->method('findByQuery')->with('searchTerm')->willReturn([
            'resultItem1' => 'something',
            'resultItem2' => 'something',
        ]);

        $view->expects(self::once())->method('assignMultiple')->with(self::callback(fn (array $variables) => isset($variables['results'])
            && $variables['results'] === [
                'resultItem1' => 'something',
                'resultItem2' => 'something',
            ]));

        $subject->search($request);
    }

    /**
     * @test
     */
    public function searchActionRendersSearchTemplate(): void
    {
        $subject = new SearchController();
        $this->setProperty($subject, 'container', $this->container->reveal());

        $query = new ParameterBag(['q' => 'searchTerm', 'page' => '1']);

        $request = $this->prophesize(Request::class);
        $request->query = $query;

        $this->view->render('search/search.html.twig', Argument::any())->shouldBeCalledTimes(1);

        $subject->search($request->reveal());
    }

    private function setProperty($instance, string $property, $value)
    {
        $propertyReflection = new \ReflectionProperty($instance, $property);
        $propertyReflection->setAccessible(true);
        $propertyReflection->setValue($instance, $value);
    }
}
