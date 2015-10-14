<?php
/**
 * @file
 */

namespace CultuurNet\UDB3\UDB2;

use CultuurNet\Auth\ConsumerCredentials;
use CultuurNet\Auth\TokenCredentials;
use CultuurNet\Entry\EntryAPI;
use Guzzle\Log\ClosureLogAdapter;
use Guzzle\Plugin\Log\LogPlugin;

final class EntryAPIImprovedFactory implements EntryAPIImprovedFactoryInterface
{
    /**
     * @var Consumer
     */
    private $consumer;

    /**
     * @param Consumer $consumer
     */
    public function __construct(
        Consumer $consumer
    ) {
        $this->consumer = $consumer;
    }

    /**
     * @param TokenCredentials $tokenCredentials
     * @return EntryAPI
     */
    public function withTokenCredentials(TokenCredentials $tokenCredentials)
    {
        $entryApi = new EntryAPI(
            $this->consumer->getTargetUrl(),
            $this->consumer->getConsumerCredentials(),
            $tokenCredentials
        );

        $this->addCommandLineLogger($entryApi);

        return $entryApi;
    }

    /**
     * @param ConsumerCredentials $consumerCredentials
     * @param TokenCredentials $tokenCredentials
     * @return EntryAPI
     */
    public function withConsumerAndTokenCredentials(
        ConsumerCredentials $consumerCredentials,
        TokenCredentials $tokenCredentials
    ) {
        $entryApi = new EntryAPI(
            $this->consumer->getTargetUrl(),
            $consumerCredentials,
            $tokenCredentials
        );

        $this->addCommandLineLogger($entryApi);

        return $entryApi;
    }

    /**
     * @param EntryAPI $entryApi
     */
    public function addCommandLineLogger(EntryAPI $entryApi)
    {
        // Print request and response for debugging purposes. Only on CLI.
        if (PHP_SAPI === 'cli') {
            $adapter = new ClosureLogAdapter(
                function ($message, $priority, $extras) {
                    print $message;
                }
            );

            $format = "\n\n# Request:\n{request}\n\n# Response:\n{response}\n\n# Errors: {curl_code} {curl_error}\n\n";
            $log = new LogPlugin($adapter, $format);

            $entryApi->getHttpClientFactory()->addSubscriber($log);
        }
    }
}
