<?php

namespace App\MessageHandler;

use App\Entity\Comment;
use App\Message\CommentMessage;
use App\Repository\CommentRepository;
use App\SpamChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class CommentMessageHandler implements MessageHandlerInterface
{
    private $spamChecker;
    private $entityManger;
    private $commentRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        SpamChecker $spamChecker,
        CommentRepository $commentRepository
    )
    {
        $this->entityManger = $entityManager;
        $this->spamChecker = $spamChecker;
        $this->commentRepository = $commentRepository;
    }

    public function __invoke(CommentMessage $message)
    {
        if(!$comment = $this->commentRepository->find($message->getId())){
            return;
        }

        if(SpamChecker::OBVIOUSLY_SPAM === $this->spamChecker->getSpamScore($comment, $message->getContext())) {
            $comment->setState(Comment::STATE_SPAM);
        } else {
            $comment->setState(Comment::STATE_PUBLISHED);
        }

        $this->entityManger->flush();
    }
}