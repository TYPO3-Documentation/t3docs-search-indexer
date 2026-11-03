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

    /**
     * The suggest endpoint gets merged hits (data + highlights), like the results page, and
     * links each one with SlugBuilder, so the modal links to the same version as the page.
     *
     * @test
     */
    public function suggestActionLinksMergedHits(): void
    {
        $elasticRepository = $this->prophesize(ElasticRepository::class);
        $elasticRepository->suggestScopes(Argument::any())->willReturn([]);
        $elasticRepository->searchDocumentsForSuggest(Argument::any())->willReturn([
            'time' => 1,
            'results' => [[
                'data' => [
                    'manual_version' => ['main', '0.25'],
                    'manual_slug' => ['p/netresearch/nr-llm/main/en-us', 'p/netresearch/nr-llm/0.25/en-us'],
                    'is_core' => false,
                ],
                'highlights' => [],
            ]],
        ]);
        $subject = new SearchController($elasticRepository->reveal());

        $response = $subject->suggest(Request::create('/suggest', 'GET', ['q' => 'openai']));
        $json = json_decode((string)$response->getContent(), true);

        self::assertSame('p/netresearch/nr-llm/0.25/en-us', $json['results'][0]['manual_slug']);
    }

    private function setProperty($instance, string $property, $value)
    {
        $propertyReflection = new \ReflectionProperty($instance, $property);
        $propertyReflection->setAccessible(true);
        $propertyReflection->setValue($instance, $value);
    }
}
