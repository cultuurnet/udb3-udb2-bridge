<?php
/**
 * @file
 */

namespace CultuurNet\UDB3\UDB2;

use CultuurNet\Auth\ConsumerCredentials;
use CultuurNet\Auth\TokenCredentials;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class EventSubscriberDecoratedEntryAPIImprovedFactory implements EntryAPIImprovedFactoryInterface
{
    /**
     * @var EntryAPIImprovedFactoryInterface
     */
    private $wrapped;

    /**
     * @var EventSubscriberInterface
     */
    private $eventSubscriber;

    /**
     * @param EntryAPIImprovedFactoryInterface $wrapped
     * @param EventSubscriberInterface $eventSubscriber
     */
    public function __construct(
        EntryAPIImprovedFactoryInterface $wrapped,
        EventSubscriberInterface $eventSubscriber
    ) {
        $this->wrapped = $wrapped;
        $this->eventSubscriber = $eventSubscriber;
    }

    public function withTokenCredentials(TokenCredentials $tokenCredentials)
    {
        $entryAPI = $this->wrapped->withTokenCredentials($tokenCredentials);

        $entryAPI->getHttpClientFactory()->addSubscriber($this->eventSubscriber);

        return $entryAPI;
    }

    public function withConsumerAndTokenCredentials(
        ConsumerCredentials $consumerCredentials,
        TokenCredentials $tokenCredentials
    ) {
        $entryAPI = $this->wrapped->withConsumerAndTokenCredentials(
            $consumerCredentials,
            $tokenCredentials
        );

        $entryAPI->getHttpClientFactory()->addSubscriber($this->eventSubscriber);

        return $entryAPI;
    }
}
