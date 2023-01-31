<?php

namespace App\Controller;

use App\Services\LinkManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UrlController extends AbstractController
{
    public function index(LinkManager $linkManager): Response
    {
        return $this->render('url/index.html.twig', [
            'resources' => $linkManager->listResources(),
        ]);
    }


    #[Route('/delete/{resource}/{fileId}', name: 'resource_delete', methods: ['GET'], requirements: ['resource' => '.+'])]
    public function delete(LinkManager $linkManager, $resource, $fileId): Response
    {
        try {

            $linkManager->delete($resource, $fileId);
            $this->addFlash('success', 'URL is deleted');
            $url = null;
        } catch (\Exception|\Error $exception) {
            $this->addFlash('error', 'Cannot delete url. (' . $exception->getMessage() . ')');
        }
        return $this->redirectToRoute('resource', ['resource' => $resource, 'url' => $url]);

    }

    #[Route('/save/{resource}', name: 'resource_save', methods: ['POST'], requirements: ['resource' => '.+'])]
    public function store(Request $request, LinkManager $linkManager, string $resource = '',): Response
    {
        try {
            $url = $request->request->get('url');
            if ($url === null) {
                throw new \Exception('missing URL');
            }
            $linkManager->store($resource, $url);
            $this->addFlash('success', 'URL is saved');
            $url = null;
        } catch (\Exception|\Error $exception) {
            $this->addFlash('error', 'Cannot save url. (' . $exception->getMessage() . ')');
        }
        return $this->redirectToRoute('resource', ['resource' => $resource, 'url' => $url]);
    }



    #[Route('/{resource}', name: 'resource', methods: ['GET'], requirements: ['resource' => '.+'])]
    #[Route('', name: 'resource_default', methods: ['GET'])]
    public function get(LinkManager $linkManager, Request $request, string $resource = 'home',): Response
    {
        return $this->render('url/details.html.twig', [
            'file' => $resource,
            'isDefault' => $resource === 'home',
            'resource' => $linkManager->retrieve($resource),
            'url' => $request->get('url', ''),
        ]);
    }

    #[Route('/', name: 'resources_new', methods: ['POST'])]
    public function new(Request $request): Response
    {
        return $this->redirectToRoute('resource', ['resource' => $request->get('resource')]);
    }

}
