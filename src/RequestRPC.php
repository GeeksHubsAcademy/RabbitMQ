<?php
declare(strict_types=1);

namespace Geekshubs\RabbitMQ;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

final class RequestRPC
{

    private ?AMQPChannel $channel;
    private Connection $connection;
    private ?AMQPMessage $message;
    private $response;
    public string $id;



    public function __construct(Connection $connection, string $callback_queue)
    {

        $this->connection = $connection;
        $this->channel = $connection->getChannel();
        list( $callback_queue, ,) = $this->channel->queue_declare(
            $callback_queue,
            false,
            false,
            false,
            false
        );

        $this->channel->basic_consume(
            $callback_queue,
            '',
            false,
            false,
            false,
            false,
            array(
                $this,
                'messageRecived'
            )
        );
    }

    public  function messageRecived($resp){
        $this->response = $resp->body;
        $resp->delivery_info['channel']->basic_ack($resp->delivery_info['delivery_tag']);
    }

    public function call(string $id='', string $queue = '', string $queue_return='', string $exchange = '', string $routing_key = '', string $message='')
    {
        $this->response = null;
        $this->message = null;
        $this->callback_queue = $queue_return;
        $this->channel->queue_bind($queue, $exchange, $routing_key);
        $msg = new AMQPMessage(
            $message,
            array(
                'correlation_id'=>$id,
                'reply_to'=>$queue_return)
        );
        $this->channel->basic_publish($msg, $exchange, $routing_key);
        while (!$this->response){
            $this->channel->wait();
        }

        if (!is_null($this->response)) {
            return $this->response;
        }
    }

    public function response(array $message, AMQPMessage $AMQPMessage):void{
        try{
            $transaction = json_encode($message);
            $message = new AMQPMessage(
                $transaction,
                array('correlation_id'=> $AMQPMessage->get('correlation_id'))
            );
            $AMQPMessage->get('channel')->basic_publish(
                $message,
                '',
                $AMQPMessage->get('reply_to')
            );

     }catch (\Exception $ex){
            throw new \Exception("Error repsonse message ->". $ex->getMessage());
        }

    }
}