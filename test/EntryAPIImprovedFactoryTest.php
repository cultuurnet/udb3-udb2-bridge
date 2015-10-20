<?php
/**
 * Created by PhpStorm.
 * User: nicolas
 * Date: 15/10/15
 * Time: 14:16
 */

namespace CultuurNet\UDB3\UDB2;

use CultuurNet\Auth\ConsumerCredentials;
use CultuurNet\Auth\TokenCredentials;
use CultuurNet\Entry\EntryAPI;

class EntryAPIImprovedFactoryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Consumer
     */
    protected $consumer;

    /**
     * @var EntryAPIImprovedFactory
     */
    protected $entryAPIImprovedFactory;

    public function setUp()
    {
        $consumerCredentials = new ConsumerCredentials('consumerkey', 'consumersecret');
        $targetUrl = 'http://acc.uitid.be/uitid/rest/entry/test.rest.uitdatabank.be/api/v3';
        $this->consumer = new Consumer($targetUrl, $consumerCredentials);
        $this->entryAPIImprovedFactory = new EntryAPIImprovedFactory($this->consumer);
    }

    /**
     * @test
     */
    public function it_returns_an_entryapi_with_token_credentials()
    {
        $tokenCredentials = new TokenCredentials('token', 'tokensecret');
        $entryAPI = $this->entryAPIImprovedFactory->withTokenCredentials($tokenCredentials);

        $expectedEntryAPI = new EntryAPI(
            $this->consumer->getTargetUrl(),
            $this->consumer->getConsumerCredentials(),
            $tokenCredentials
        );

        $this->assertEquals($expectedEntryAPI, $entryAPI);
    }

    /**
     * @test
     */
    public function it_returns_an_entryapi_with_consumer_and_token_credentials()
    {
        $tokenCredentials = new TokenCredentials('token', 'tokensecret');
        $consumerCredentialsPostingUser = new ConsumerCredentials(
            'consumerkeyPostingUser',
            'consumersecretPostingUser'
        );
        $entryAPI = $this->entryAPIImprovedFactory->withConsumerAndTokenCredentials(
            $consumerCredentialsPostingUser,
            $tokenCredentials
        );

        $expectedEntryAPI = new EntryAPI(
            $this->consumer->getTargetUrl(),
            $consumerCredentialsPostingUser,
            $tokenCredentials
        );

        $this->assertEquals($expectedEntryAPI, $entryAPI);
    }
}
