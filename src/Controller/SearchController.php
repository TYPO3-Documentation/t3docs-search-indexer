<?php

namespace App\Controller;

use App\Dto\SearchDemand;
use App\Repository\ElasticRepository;
use Elastica\Exception\InvalidException;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SearchController extends AbstractController
{
    public function __construct(private readonly ElasticRepository $elasticRepository)
    {
    }


    /**
     * @return Response
     */
    #[Route(path: '/', name: 'index')]
    public function index(): Response
    {
        return $this->render('search/index.html.twig');
    }

    /**
     * @return Response
     * @throws InvalidException
     */
    #[Route(path: '/search', name: 'searchresult')]
    public function search(Request $request): Response
    {
        if ($request->query->get('q', '') === '') {
            return $this->redirectToRoute('index');
        }
        $searchDemand = SearchDemand::createFromRequest($request);

        return $this->render('search/search.html.twig', [
            'q' => $searchDemand->getQuery(),
            'filters' => $request->get('filters', []),
            'results' => $this->elasticRepository->findByQuery($searchDemand),
        ]);
    }

    /**
     * @return Response
     * @throws InvalidException|JsonException
     */
    #[Route(path: '/suggest', name: 'suggest')]
    public function suggest(Request $request): Response
    {
        $searchDemand = SearchDemand::createFromRequest($request);

        $results = $this->elasticRepository->suggest($searchDemand);
        $suggestions = [];
        foreach ($results['results'] as $result) {
            $hit = $result->getData();
            $suggestions[] = [
                'label' => $hit['snippet_title'],
                'value' => $hit['snippet_title'],
                'url' => 'https://docs.typo3.org/' . $hit['manual_slug'] . '/' . $hit['relative_url'] . '#' . $hit['fragment'],
                'group' => $hit['manual_title'],
                'content' => \mb_substr((string)$hit['snippet_content'], 0, 100)
            ];
        }
        $jsonBody =  \json_encode($suggestions, JSON_THROW_ON_ERROR);

        $response = new Response();
        $response->setContent($jsonBody);
        return $response;
    }
}
