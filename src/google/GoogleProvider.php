<?php

namespace sitkoru\contextcache\google;


use Google\AdsApi\AdWords\AdWordsServices;
use Google\AdsApi\AdWords\AdWordsSession;
use Google\AdsApi\AdWords\AdWordsSessionBuilder;
use Google\AdsApi\AdWords\v201702\cm\AdGroupService;
use Google\AdsApi\Common\OAuth2TokenBuilder;
use Google\Auth\CredentialsLoader;
use sitkoru\contextcache\common\ICacheProvider;

class GoogleProvider
{

    public $adGroups;

    public $ads;

    public $campaigns;

    public $keywords;
    /**
     * @var
     */
    private $oAuthFilePath;

    /**
     * @var
     */
    private $customerId;
    /**
     * @var
     */
    private $refreshToken;

    /**
     * GoogleProvider constructor.
     * @param int            $customerId
     * @param string         $oAuthFilePath
     * @param null|string    $refreshToken
     * @param ICacheProvider $cacheProvider
     */
    public function __construct(
        int $customerId,
        string $oAuthFilePath,
        ?string $refreshToken = null,
        ICacheProvider $cacheProvider
    ) {

        $this->customerId = $customerId;
        $this->refreshToken = $refreshToken;
        $this->oAuthFilePath = $oAuthFilePath;

        $services = new AdWordsServices();
        $session = $this->getSession();
        /**
         * @var AdGroupService $adGroupService
         */
        $adGroupService = $services->get($session, AdGroupService::class);
        $this->adGroups = new GoogleAdGroupsProvider($adGroupService, $cacheProvider);


    }

    private function getSession(): AdWordsSession
    {
        $builder = $this->getSessionBuilder();

        return $builder->build();
    }

    private function getSessionBuilder(): AdWordsSessionBuilder
    {

        $oAuth2Credential = $this->getOAuthCredentials();

        $builder = (new AdWordsSessionBuilder())
            ->fromFile($this->oAuthFilePath)
            ->withOAuth2Credential($oAuth2Credential)
            ->withClientCustomerId($this->customerId);

        return $builder;
    }

    public function getOAuthCredentials(): CredentialsLoader
    {
        $authFile = $this->oAuthFilePath;
        $oauthBuilder = (new OAuth2TokenBuilder())
            ->fromFile($authFile);
        if ($this->refreshToken) {
            $oauthBuilder->withRefreshToken($this->refreshToken);
        }

        return $oauthBuilder
            ->build();
    }
}