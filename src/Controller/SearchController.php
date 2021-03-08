<?php

/**
 * Created by PhpStorm.
 * User: mathiasschreiber
 * Date: 16.01.18
 * Time: 09:07
 */
namespace App\Controller;

use App\Repository\ElasticRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
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
        $query = $request->query->get('q');

        return $this->render('search/search.html.twig', [
            'q' => $query,
            'results' => $elasticRepository->findByQuery($query),
        ]);
    }
}
