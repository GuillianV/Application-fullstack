<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Lock\LockFactory;

class LinkManager
{

    public function __construct(private readonly LockFactory $lockFactory, private readonly string $baseDir = "")
    {
        @mkdir($this->baseDir, permissions: 0777, recursive: true);
    }

    public function listResources()
    {
        $finder = new Finder();
        return $finder->files()->in($this->baseDir);
    }

    public function store($resourceName, $url)
    {
        $lock = $this->lockFactory->createLock('file-' . $resourceName);

        $lock->acquire(true);
        $resource = $this->getResource($resourceName);

        $content = [];
        try {
            $content = $this->getDetails($url);
        } catch (\Throwable $throwable) {
        }
        $content['url'] = $url;
        $content['storedAt'] = new \DateTime();
        unset($resource[sha1($url)]);
        $resource[sha1($url)] = $content;
        $this->updateResource($resourceName, $resource);
        $lock->release();
    }

    public function retrieve($resourceName)
    {
        $lock = $this->lockFactory->createLock('file-' . $resourceName);
        $lock->acquire(true);
        $content = $this->getResource($resourceName);
        $lock->release();
        return $content;
    }

    private function getResource($resourceName)
    {
        $content = @file_get_contents($this->baseDir . "/" . $resourceName);
        if ($content === null) {
            return [];
        }
        return json_decode($content, true) ?? [];
    }

    private function updateResource($resourceName, $content)
    {
        return file_put_contents($this->baseDir . "/" . $resourceName, json_encode($content));
    }

    private function getDetails($url)
    {
        $options = stream_context_create(array(
            "ssl" => array(
                "verify_peer" => false,
                "verify_peer_name" => false,
            ),
        ));
        $html = file_get_contents($url, context: $options) ?? "";
        $doc = new DomDocument();
        @$doc->loadHTML($html);
        $nodes = $doc->getElementsByTagName('title');
        $title = $nodes?->item(0)?->nodeValue;
        $xpath = new DOMXPath($doc);
        $query = '//*/meta[starts-with(@property, \'og:\')]';
        $metas = $xpath->query($query);
        $rmeta = [];
        foreach ($metas as $meta) {
            $property = $meta->getAttribute('property');
            $content = $meta->getAttribute('content');
            $property = str_replace("og:", "", $property);
            $rmeta[$property] = $content;
        }
        $settings = ["page" => $title, ...$rmeta];

        $metas = $doc->getElementsByTagName('meta');
        for ($i = 0; $i < $metas->length; $i++) {
            $meta = $metas->item($i);
            $settings[$meta->getAttribute('name')] = $meta->getAttribute('content');
        }
        if (isset($settings['keywords'])) {
            $settings['keywords'] = explode(',', $settings['keywords']);
        }
        return $settings;
    }

    public function delete($resourceName, $fileId)
    {
        $lock = $this->lockFactory->createLock('file-' . $resourceName);
        $lock->acquire(true);
        $content = $this->getResource($resourceName);
        unset($content[$fileId]);

        $this->updateResource($resourceName, $content);
        $lock->release();
        if (count($content) === 0) {
            $this->deleteFile($resourceName);
        }
    }

    private function deleteFile($resourceName)
    {
        $lock = $this->lockFactory->createLock('file-' . $resourceName);
        $lock->acquire(true);
        @unlink($this->baseDir . "/" . $resourceName);
        $lock->release();
    }

}