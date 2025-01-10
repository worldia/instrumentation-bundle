<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace App\Controller;

use App\Doctrine\Entity\Post;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class Doctrine extends AbstractController
{
    #[Route('/doctrine')]
    public function list(EntityManagerInterface $entityManager): Response
    {
        $posts = $entityManager->getRepository(Post::class)->findAll();

        $href = $this->getTraceLink();

        $content = array_map(fn (Post $post) => $post->getText(), $posts);

        $content = implode('<br>', $content);

        return new Response(\sprintf('%s<br><br>Trace: %s', $content, $href));
    }
}
