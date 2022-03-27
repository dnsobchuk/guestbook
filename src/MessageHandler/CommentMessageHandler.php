<?php

namespace App\MessageHandler;

use App\Entity\Comment;
use App\Message\CommentMessage;
use App\Repository\CommentRepository;
use App\SpamChecker;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\NotificationEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\WorkflowInterface;

class CommentMessageHandler implements MessageHandlerInterface
{
    private $spamChecker;
    private $entityManger;
    private $commentRepository;
    private $bus;
    private $workflow;
    private $mailer;
    private $adminEmail;
    private $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        SpamChecker $spamChecker,
        CommentRepository $commentRepository,
        MessageBusInterface $bus,
        WorkflowInterface $commentStateMachine,
        MailerInterface $mailer,
        string $adminEmail,
        LoggerInterface $logger = null
    )
    {
        $this->entityManger = $entityManager;
        $this->spamChecker = $spamChecker;
        $this->commentRepository = $commentRepository;
        $this->bus = $bus;
        $this->workflow = $commentStateMachine;
        $this->mailer = $mailer;
        $this->adminEmail = $adminEmail;
        $this->logger = $logger;
    }

    public function __invoke(CommentMessage $message)
    {
        if(!$comment = $this->commentRepository->find($message->getId())){
            return;
        }

        if($this->workflow->can($comment, 'accept')) {
            $score = $this->spamChecker->getSpamScore($comment, $message->getContext());
            $transition = 'accept';
            if(SpamChecker::OBVIOUSLY_SPAM === $score) {
                $transition = 'acept';
            } else if(SpamChecker::MAY_BE_SPAM === $score) {
                $transition = 'might_be_spam';
            }
            $this->workflow->apply($comment, $transition);
            $this->bus->dispatch($message);
        } elseif($this->workflow->can($comment, 'publish')
            || $this->workflow->can($comment, 'publish_ham')) {
          $this->mailer->send((new NotificationEmail())
              ->subject('New comment posted')
              ->htmlTemplate('emails/comment_notification.html.twig')
              ->from($this->adminEmail)
              ->to($this->adminEmail)
              ->context(['comment' => $comment])
          );
        } elseif($this->logger) {
            $this->logger->debug('Droping comment message', [
                'comment' => $comment->getId(),
                'state' => $comment->getState()
            ]);
        }
    }
}