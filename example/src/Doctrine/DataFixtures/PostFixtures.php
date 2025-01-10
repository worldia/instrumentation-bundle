<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace App\Doctrine\DataFixtures;

use App\Doctrine\Entity\Post;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class PostFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        for ($i = 1; $i <= 20; ++$i) {
            $post = new Post();
            $post->setText('Post '.$i);

            $manager->persist($post);
        }

        $manager->flush();
    }
}
