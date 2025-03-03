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
        $elasticRepository = $this->prophesize(ElasticRepository::class);
        $subject = new SearchController($elasticRepository->reveal());
        $this->setProperty($subject, 'container', $this->container->reveal());

        $this->view->render('search/index.html.twig', [])->shouldBeCalledOnce();

        $subject->index();
    }

    /**
     * @test
     */
    public function searchActionAssignsQueryToTemplate(): void
    {
        $elasticRepository = $this->prophesize(ElasticRepository::class);
        $elasticRepository->findByQuery(Argument::any())->willReturn([]);
        $subject = new SearchController($elasticRepository->reveal());
        $this->setProperty($subject, 'container', $this->container->reveal());

        $query = new ParameterBag(['q' => 'searchTerm', 'page' => '1']);

        $request = $this->prophesize(Request::class);
        $request->query = $query;

        $this->view->render(Argument::any(), Argument::that(fn (array $variables) => isset($variables['q'])
            && $variables['q'] === 'searchTerm'))->shouldBeCalledOnce();

        $subject->search($request->reveal());
    }

    /**
     * @test
     */
    public function searchActionAssignsResultsToTemplate(): void
    {
        $searchResults = [
            'resultItem1' => 'something',
            'resultItem2' => 'something',
        ];

        $elasticRepository = $this->prophesize(ElasticRepository::class);
        $elasticRepository->findByQuery(Argument::any())->willReturn($searchResults)->shouldBeCalledOnce();
        $subject = new SearchController($elasticRepository->reveal());
        $this->setProperty($subject, 'container', $this->container->reveal());

        $query = new ParameterBag(['q' => 'searchTerm', 'page' => '1']);

        $request = $this->prophesize(Request::class);
        $request->query = $query;

        $this->view->render(
            Argument::any(),
            Argument::that(fn (array $variables) => isset($variables['results']) && $variables['results'] === $searchResults)
        )->shouldBeCalledOnce();

        $subject->search($request->reveal());
    }

    /**
     * @test
     */
    public function searchActionRendersSearchTemplate(): void
    {
        $elasticRepository = $this->prophesize(ElasticRepository::class);
        $elasticRepository->findByQuery(Argument::any())->willReturn([]);
        $subject = new SearchController($elasticRepository->reveal());
        $this->setProperty($subject, 'container', $this->container->reveal());

        $query = new ParameterBag(['q' => 'searchTerm', 'page' => '1']);

        $request = $this->prophesize(Request::class);
        $request->query = $query;

        $this->view->render('search/search.html.twig', Argument::any())->shouldBeCalledOnce();

        $subject->search($request->reveal());
    }

    private function setProperty($instance, string $property, $value)
    {
        $propertyReflection = new \ReflectionProperty($instance, $property);
        $propertyReflection->setAccessible(true);
        $propertyReflection->setValue($instance, $value);
    }
}
