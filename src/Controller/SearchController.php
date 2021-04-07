<?php


namespace App\Controller;

use App\Dto\SearchDemand;
use App\Repository\ElasticRepository;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SearchController extends AbstractController
{
    /**
     * @Route("/", name="index")
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexAction(): Response
    {
        return $this->render('search/index.html.twig');
    }

    /**
     * @Route("/search", name="searchresult")
     * @param Request $request
     * @return Response
     * @throws \Elastica\Exception\InvalidException
     */
    public function searchAction(Request $request): Response
    {
        $elasticRepository = new ElasticRepository();
        $searchDemand = SearchDemand::createFromRequest($request);

        return $this->render('search/search.html.twig', [
            'q' => $searchDemand->getQuery(),
            'filters' => $request->get('filters', []),
            'results' => $elasticRepository->findByQuery($searchDemand),
        ]);
    }

    /**
     * @Route("/suggest", name="suggest")
     * @param Request $request
     * @return Response
     * @throws \Elastica\Exception\InvalidException
     */
    public function suggestAction(Request $request): Response
    {
        $elasticRepository = new ElasticRepository();
        $searchDemand = SearchDemand::createFromRequest($request);

        $results = $elasticRepository->suggest($searchDemand);
        $suggestions = [];
        foreach ($results['results'] as $result) {

            $hit = $result->getData();
            $suggestions[] = [
                'label' => $hit['snippet_title'],
                'value' => $hit['snippet_title'],
                'url' => 'https://docs.typo3.org/' . $hit['manual_slug'] . '/' . $hit['relative_url'] .'#' . $hit['fragment'],
                'group' => $hit['manual_title'],
                'content' => \mb_substr($hit['snippet_content'], 0, 100)
            ];
        }
        $jsonBody =  \json_encode($suggestions);

        $response = new Response();
        $response->setContent($jsonBody);
        return $response;
    }
}
