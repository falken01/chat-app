<?php

namespace App\Controller;


use App\Entity\Message;
use App\Entity\Participant;
use App\Entity\User;
use App\Repository\ConversationRepository;
use App\Entity\Conversation;
use App\Repository\MessageRepository;
use App\Repository\ParticipantRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\PublisherInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @Route("/messages", name="messages.")
 */
class MessageController extends AbstractController
{
    const ATTRIBUTES_TO_SERIALIZE=['id','content','createdAt','mine'];
    private $em;
    private $message;
    private $userRepository;
    private $participant;
    public $publisher;
    public function __construct(EntityManagerInterface $em, MessageRepository $message, UserRepository $userRepository, ParticipantRepository $participant, PublisherInterface $publisher)
    {
        $this->em = $em;
        $this->message=$message;
        $this->userRepository = $userRepository;
        $this->participant = $participant;
        $this->publisher = $publisher;
    }

    /**
     * @Route("/{id}", name="getMessage", methods={"GET"})
     * @param Request $request
     * @param Conversation $conversation
     * @return Response
     */
    public function index(Request $request, $id, ConversationRepository $conversation)
    {
        $conversation = $this->em->getRepository(Conversation::class)->find($id);
        $this->denyAccessUnlessGranted('view', $conversation);
        $messages = $this->em->getRepository(Message::class)->findMessagesByConversationsId($id);
        array_map(function($message){
           $message->setMine(
               $message->getUser()->getId() == $this->getUser()->getId()?true:false
           );
        },$messages);
        //dd($messages);
        return $this->json($messages,RESPONSE::HTTP_OK,[],[
            'attributes'=>self::ATTRIBUTES_TO_SERIALIZE
        ]);
    }

    /**
     * @Route("/{id}",name="newMessage", methods={"POST"})
     * @param Request $request
     * @param Conversation $conversation
     * @param $id
     * @param SerializerInterface $serializer
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws \Exception
     */
    public function newMessage(Request $request, Conversation $conversation, $id, SerializerInterface $serializer) {

        $user = $this->userRepository->findOneBy(['id'=>1]); // $this->getUser();
        $recipient = $this->participant->findParticipantByConvIdAndUser($conversation->getId(), $user->getId());
        $conversation = $this->em->getRepository(Conversation::class)->find($id);
        $content = $request->get('content',"test"); //potential trouble
        $message = new Message();
        $message->setContent($content);
        $message->setUser($user); //just user
        $message->setMine(true);
        $conversation->addMessage($message);
        $conversation->setLastMessage($message);
        $this->em->getConnection()->beginTransaction();
        try {
            $this->em->persist($message);
            $this->em->persist($conversation);
            $this->em->flush();
            $this->em->commit();
        } catch (\Exception $e)
        {
            $this->em->rollback();
            throw $e;
        }

        $message->setMine(false);
        $messageSerialized = $serializer->serialize($message,'json', [
            'attributes'=>['id','content','createdAt','mine','conversation'=>['id']]
        ]);
        $update = new Update([sprintf("/conversations/%s",$conversation->getId()),
                              sprintf("/conversations/%s",$conversation->getId())], //topics
            $messageSerialized,
            [
                sprintf('%s',$recipient->getUser()->getUsername())
            ] //targets
        );
        $this->publisher->__invoke($update);
        return $this->json($message,RESPONSE::HTTP_OK,[],[
            'attributes'=>self::ATTRIBUTES_TO_SERIALIZE
        ]);
    }
}
