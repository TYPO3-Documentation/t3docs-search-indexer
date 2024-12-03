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
        $searchDemand = SearchDemand::createFromRequest($request);
        if ($searchDemand->getQuery() === '' && empty($searchDemand->getFilters())) {
            return $this->redirectToRoute('index');
        }

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

        $jsonData['results'] = array_map(static function ($result) use ($searchDemand) {
            $data = $result->getData();

            // If a major version is seeked, replace the version in the slug to match.
            if ($searchDemand->getFilters()['major_versions'] ?? null) {
                $targetVersion = null;
                foreach ($data['manual_version'] as $version) {
                    if (str_starts_with($version, $searchDemand->getFilters()['major_versions'][0].'.')) {
                        $targetVersion = $version;
                        break;
                    }
                }

                if ($targetVersion) {
                    $data['manual_slug'] = str_replace($data['manual_version'][0], $targetVersion, $data['manual_slug']);
                }
            }

            return $data;
        }, $searchResults['results']);

        return new JsonResponse($jsonData);
    }
}
