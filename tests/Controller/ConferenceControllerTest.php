<?php

namespace App\Tests\Controller;


use App\Repository\CommentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Panther\PantherTestCase;

class ConferenceControllerTest extends PantherTestCase
{
    public function testIndex(): void
    {
        $client = static::createPantherClient();
        $client->request('GET', '/');

        self::assertPageTitleContains('Conference Guestbook');
        self::assertSelectorTextContains('h2', 'Give your feedback');
    }

    public function testCommentSubmission(): void
    {
        $client = static::createClient();
        $client->request('GET', '/conference/amsterdam-2019');
        $client->submitForm('Submit', [
            'comment_form[author]' => 'Fabien',
            'comment_form[text]' => 'Some feedback from an automated functional test',
            'comment_form[email]' => $email = 'me@automat.ed',
            'comment_form[photo]' => dirname(__DIR__, 2).'/public/images/under-construction.gif',
        ]);
        self::assertResponseRedirects();

        // simulate comment validation
        $comment = static::$container->get(CommentRepository::class)->findOneByEmail($email);
        $comment->setState('published');
        static::$container->get(EntityManagerInterface::class)->flush();

        $client->followRedirect();
        self::assertSelectorExists('div:contains("There are 3 comments")');
    }

    public function testConferencePage(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        self::assertCount(2, $crawler->filter('h4'));

        $client->clickLink('View');

        self::assertPageTitleContains('Amsterdam');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h2', 'Amsterdam 2019');
        self::assertSelectorExists('div:contains("There are 2 comments")');
    }
}
