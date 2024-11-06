<?php

namespace App\Controller;

use App\Dto\SearchDemand;
use App\Repository\ElasticRepository;
use Elastica\Exception\InvalidException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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
            'searchScope' => $searchDemand->getScope(),
            'filters' => $request->get('filters', []),
            'results' => $this->elasticRepository->findByQuery($searchDemand),
        ]);
    }

    #[Route(path: '/suggest', name: 'suggest')]
    public function suggest(Request $request): Response
    {
        $searchDemand = SearchDemand::createFromRequest($request);
        $jsonData = [
            'demand' => $searchDemand->toArray(),
            'suggest' => $this->elasticRepository->suggestScopes($searchDemand)
        ];

        $searchResults = $this->elasticRepository->searchDocumentsForSuggest($searchDemand);
        $jsonData['time'] = $searchResults['time'];

        $jsonData['results'] = array_map(static function ($result) {
            return $result->getData();
        }, $searchResults['results']);

        return new JsonResponse($jsonData);
    }

    #[Route(path: '/suggest/list', name: 'suggest-list')]
    public function suggestList(Request $request): Response
    {
        $searchDemand = SearchDemand::createFromRequest($request);
        $jsonData = [
            'demand' => $searchDemand->toArray(),
            'suggest' => $this->elasticRepository->suggestScopes($searchDemand)
        ];

        return new JsonResponse($jsonData);
    }

    #[Route(path: '/suggest/results', name: 'suggest-results')]
    public function suggestResults(Request $request): Response
    {
        $searchDemand = SearchDemand::createFromRequest($request);

        $searchResults = $this->elasticRepository->searchDocumentsForSuggest($searchDemand);
        $jsonData['time'] = $searchResults['time'];

        $jsonData['results'] = array_map(static function ($result) {
            return $result->getData();
        }, $searchResults['results']);

        return new JsonResponse($jsonData);
    }
}
