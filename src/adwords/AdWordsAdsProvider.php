<?php

namespace sitkoru\contextcache\adwords;

use function count;
use ErrorException;
use Exception;
use Google\AdsApi\AdWords\AdWordsSession;
use Google\AdsApi\AdWords\v201809\cm\Ad;
use Google\AdsApi\AdWords\v201809\cm\AdGroupAd;
use Google\AdsApi\AdWords\v201809\cm\AdGroupAdOperation;
use Google\AdsApi\AdWords\v201809\cm\AdGroupAdService;
use Google\AdsApi\AdWords\v201809\cm\AdGroupAdStatus;
use Google\AdsApi\AdWords\v201809\cm\AdOperation;
use Google\AdsApi\AdWords\v201809\cm\AdService;
use Google\AdsApi\AdWords\v201809\cm\ApiException;
use Google\AdsApi\AdWords\v201809\cm\Operand;
use Google\AdsApi\AdWords\v201809\cm\Operator;
use Google\AdsApi\AdWords\v201809\cm\Predicate;
use Google\AdsApi\AdWords\v201809\cm\PredicateOperator;
use Google\AdsApi\AdWords\v201809\cm\Selector;
use Google\AdsApi\AdWords\v201809\cm\TempAdUnionId;
use Google\AdsApi\AdWords\v201809\cm\TemplateAd;
use sitkoru\contextcache\common\ContextEntitiesLogger;
use sitkoru\contextcache\common\ICacheProvider;
use sitkoru\contextcache\common\IEntitiesProvider;
use sitkoru\contextcache\common\models\UpdateResult;
use sitkoru\contextcache\helpers\ArrayHelper;
use Throwable;

class AdWordsAdsProvider extends AdWordsEntitiesProvider implements IEntitiesProvider
{
    /**
     * @var AdGroupAdService
     */
    private $adGroupAdService;

    /**
     * @var array
     */
    private static $fields = [
        'AccentColor',
        'AdGroupId',
        'AdStrengthInfo',
        'AdType',
        'AdvertisingId',
        'AllowFlexibleColor',
        'Automated',
        'BaseAdGroupId',
        'BaseCampaignId',
        'BusinessName',
        'CallOnlyAdBusinessName',
        'CallOnlyAdCallTracked',
        'CallOnlyAdConversionTypeId',
        'CallOnlyAdCountryCode',
        'CallOnlyAdDescription1',
        'CallOnlyAdDescription2',
        'CallOnlyAdDisableCallConversion',
        'CallOnlyAdPhoneNumber',
        'CallOnlyAdPhoneNumberVerificationUrl',
        'CallToActionText',
        'CreationTime',
        'CreativeFinalAppUrls',
        'CreativeFinalMobileUrls',
        'CreativeFinalUrlSuffix',
        'CreativeFinalUrls',
        'CreativeTrackingUrlTemplate',
        'CreativeUrlCustomParameters',
        'Description',
        'Description1',
        'Description2',
        'DevicePreference',
        'Dimensions',
        'DisplayUploadAdGmailTeaserBusinessName',
        'DisplayUploadAdGmailTeaserDescription',
        'DisplayUploadAdGmailTeaserHeadline',
        'DisplayUploadAdGmailTeaserLogoImage',
        'DisplayUrl',
        'ExpandedDynamicSearchCreativeDescription2',
        'ExpandedTextAdDescription2',
        'ExpandedTextAdHeadlinePart3',
        'ExpandingDirections',
        'FileSize',
        'FormatSetting',
        'GmailHeaderImage',
        'GmailMarketingImage',
        'GmailTeaserBusinessName',
        'GmailTeaserDescription',
        'GmailTeaserHeadline',
        'GmailTeaserLogoImage',
        'Headline',
        'HeadlinePart1',
        'HeadlinePart2',
        'Height',
        'Id',
        'ImageCreativeName',
        'IndustryStandardCommercialIdentifier',
        'IsCookieTargeted',
        'IsTagged',
        'IsUserInterestTargeted',
        'Labels',
        'LandscapeLogoImage',
        'LogoImage',
        'LongHeadline',
        'MainColor',
        'MarketingImage',
        'MarketingImageCallToActionText',
        'MarketingImageCallToActionTextColor',
        'MarketingImageDescription',
        'MarketingImageHeadline',
        'MediaId',
        'MimeType',
        'MultiAssetResponsiveDisplayAdAccentColor',
        'MultiAssetResponsiveDisplayAdAllowFlexibleColor',
        'MultiAssetResponsiveDisplayAdBusinessName',
        'MultiAssetResponsiveDisplayAdCallToActionText',
        'MultiAssetResponsiveDisplayAdDescriptions',
        'MultiAssetResponsiveDisplayAdDynamicSettingsPricePrefix',
        'MultiAssetResponsiveDisplayAdDynamicSettingsPromoText',
        'MultiAssetResponsiveDisplayAdFormatSetting',
        'MultiAssetResponsiveDisplayAdHeadlines',
        'MultiAssetResponsiveDisplayAdLandscapeLogoImages',
        'MultiAssetResponsiveDisplayAdLogoImages',
        'MultiAssetResponsiveDisplayAdLongHeadline',
        'MultiAssetResponsiveDisplayAdMainColor',
        'MultiAssetResponsiveDisplayAdMarketingImages',
        'MultiAssetResponsiveDisplayAdSquareMarketingImages',
        'MultiAssetResponsiveDisplayAdYouTubeVideos',
        'Path1',
        'Path2',
        'PolicySummary',
        'PricePrefix',
        'ProductImages',
        'ProductVideoList',
        'PromoText',
        'ReadyToPlayOnTheWeb',
        'ReferenceId',
        'ResponsiveSearchAdDescriptions',
        'ResponsiveSearchAdHeadlines',
        'ResponsiveSearchAdPath1',
        'ResponsiveSearchAdPath2',
        'RichMediaAdCertifiedVendorFormatId',
        'RichMediaAdDuration',
        'RichMediaAdImpressionBeaconUrl',
        'RichMediaAdName',
        'RichMediaAdSnippet',
        'RichMediaAdSourceUrl',
        'RichMediaAdType',
        'ShortHeadline',
        'SourceUrl',
        'SquareMarketingImage',
        'Status',
        'SystemManagedEntitySource',
        'TemplateAdDuration',
        'TemplateAdName',
        'TemplateAdUnionId',
        'TemplateElementFieldName',
        'TemplateElementFieldText',
        'TemplateElementFieldType',
        'TemplateId',
        'TemplateOriginAdId',
        'UniqueName',
        'UniversalAppAdDescriptions',
        'UniversalAppAdHeadlines',
        'UniversalAppAdHtml5MediaBundles',
        'UniversalAppAdImages',
        'UniversalAppAdMandatoryAdText',
        'UniversalAppAdYouTubeVideos',
        'Url',
        'UrlData',
        'Urls',
        'VideoTypes',
        'Width',
        'YouTubeVideoIdString'
    ];

    /**
     * @var AdService
     */
    private $adService;

    public function __construct(
        AdGroupAdService $adGroupService,
        AdService $adService,
        ICacheProvider $cacheProvider,
        AdWordsSession $adWordsSession,
        ContextEntitiesLogger $logger
    ) {
        parent::__construct($cacheProvider, $adWordsSession, $logger);
        $this->collection = 'adGroupAds';
        $this->adGroupAdService = $adGroupService;
        $this->keyField = 'ad.id';
        $this->adService = $adService;
    }

    /**
     * @param array $ids
     * @param array $predicates
     *
     * @return AdGroupAd[]
     *
     * @throws ApiException
     */
    public function getAll(array $ids, array $predicates = []): array
    {
        $notFound = $ids;
        /**
         * @var AdGroupAd[] $adGroupAds
         */
        $adGroupAds = $this->getFromCache($ids, 'ad.id');
        if (count($adGroupAds) > 0) {
            $found = array_keys($adGroupAds);
            $notFound = array_values(array_diff($ids, $found));
        }
        if (count($notFound) > 0) {
            $selector = new Selector();
            $selector->setFields(self::$fields);
            $predicates[] = new Predicate('Id', PredicateOperator::IN, $ids);
            $selector->setPredicates($predicates);
            $fromService = $this->doRequest(function () use ($selector): array {
                /**
                 * @var null|array
                 */
                $entries = $this->adGroupAdService->get($selector)->getEntries();
                return $entries !== null ? $entries : [];
            });
            foreach ($fromService as $adGroupAdItem) {
                $adGroupAds[$adGroupAdItem->getAd()->getId()] = $adGroupAdItem;
            }
            $this->addToCache($fromService);
        }
        return $adGroupAds;
    }

    /**
     * @param int $id
     *
     * @return AdGroupAd
     *
     * @throws ApiException
     */
    public function getOne($id): ?AdGroupAd
    {
        $ads = $this->getAll([$id]);
        if (count($ads) > 0) {
            return reset($ads);
        }
        return null;
    }

    /**
     * @param int[] $campaignIds
     * @param array $predicates
     *
     * @return AdGroupAd[]
     *
     * @throws ApiException
     */
    public function getByCampaignIds(array $campaignIds, array $predicates = []): array
    {
        $notFound = $campaignIds;

        /**
         * @var AdGroupAd[] $adGroupAds
         */
        $adGroupAds = $this->getFromCache($campaignIds, 'campaignId', 'ad.id');
        if (count($adGroupAds) > 0) {
            $found = array_unique(ArrayHelper::getColumn($adGroupAds, 'campaignId'));
            $notFound = array_values(array_diff($campaignIds, $found));
        }
        if (count($notFound) > 0) {
            $selector = new Selector();
            $selector->setFields(self::$fields);
            $predicates[] = new Predicate('CampaignId', PredicateOperator::IN, array_map(function (int $id): string {
                return $id . '';
            }, $notFound));
            $selector->setPredicates($predicates);
            $fromService = $this->doRequest(function () use ($selector): array {
                /**
                 * @var null|array
                 */
                $entries = $this->adGroupAdService->get($selector)->getEntries();
                return $entries !== null ? $entries : [];
            });
            foreach ($fromService as $adGroupAdItem) {
                /**
                 * @var AdGroupAd $adGroupAdItem
                 */
                $adGroupAds[$adGroupAdItem->getAd()->getId()] = $adGroupAdItem;
            }
            $this->addToCache($fromService);
        }
        return $adGroupAds;
    }

    /**
     * @param array $adGroupIds
     * @param array $predicates
     *
     * @return AdGroupAd[]
     *
     * @throws ApiException
     */
    public function getByAdGroupIds(array $adGroupIds, array $predicates = []): array
    {
        $notFound = $adGroupIds;

        /**
         * @var AdGroupAd[] $adGroupAds
         */
        $adGroupAds = $this->getFromCache($adGroupIds, 'adGroupId', 'ad.id');
        if (count($adGroupAds) > 0) {
            $found = array_unique(ArrayHelper::getColumn($adGroupAds, 'adGroupId'));
            $notFound = array_values(array_diff($adGroupIds, $found));
        }
        if (count($notFound) > 0) {
            $selector = new Selector();
            $selector->setFields(self::$fields);
            $predicates[] = new Predicate('AdGroupId', PredicateOperator::IN, array_map(function (int $id): string {
                return $id . '';
            }, $notFound));
            $selector->setPredicates($predicates);
            $fromService = $this->doRequest(function () use ($selector): array {
                /**
                 * @var null|array
                 */
                $entries = $this->adGroupAdService->get($selector)->getEntries();
                return $entries !== null ? $entries : [];
            });
            foreach ($fromService as $adGroupAdItem) {
                /**
                 * @var AdGroupAd $adGroupAdItem
                 */
                $adGroupAds[$adGroupAdItem->getAd()->getId()] = $adGroupAdItem;
            }
            $this->addToCache($fromService);
        }
        return $adGroupAds;
    }

    /**
     * @param AdGroupAd[] $entities
     *
     * @return UpdateResult
     *
     * @throws Exception
     * @throws Throwable
     */
    public function update(array $entities): UpdateResult
    {
        $result = new UpdateResult();
        $deleteOperations = [];
        /**
         * @var AdGroupAdOperation[] $addOperations
         */
        $addOperations = [];
        $this->logger->info('Build operations');
        foreach ($entities as $entity) {
            $newAd = clone $entity->getAd();
            $entity->setStatus(AdGroupAdStatus::DISABLED);
            $deleteOperation = new AdGroupAdOperation();
            $deleteOperation->setOperand($entity);
            $deleteOperation->setOperator(Operator::REMOVE);
            // @phpstan-ignore-next-line
            $newAd->setId(null);
            if ($newAd instanceof TemplateAd) {
                $newAd->setAdUnionId(new TempAdUnionId());
                break;
            }
            $adGroupAd = new AdGroupAd();
            $adGroupAd->setAdGroupId($entity->getAdGroupId());
            $adGroupAd->setStatus(AdGroupAdStatus::ENABLED);
            $adGroupAd->setAd($newAd);
            $addOperation = new AdGroupAdOperation();
            $addOperation->setOperand($adGroupAd);
            $addOperation->setOperator(Operator::ADD);
            $deleteOperations[$entity->getAd()->getId()] = $deleteOperation;
            $addOperations[] = $addOperation;
        }

        $this->logger->info('Add operations: ' . count($addOperations));

        if (count($addOperations) > 1000) {
            foreach (array_chunk($addOperations, self::MAX_OPERATIONS_SIZE) as $i => $addChunk) {
                /**
                 * @var AdGroupAdOperation[] $addChunk
                 */
                $this->logger->info('Add chunk #' . $i . '. Size: ' . count($addChunk));
                $jobResults = $this->runMutateJob($addChunk);
                if (is_array($jobResults)) {
                    $this->processJobResult($result, $jobResults);
                    if (!$result->success) {
                        foreach ($result->errors as $adOperationId => $errors) {
                            $adOperation = $addChunk[$adOperationId];
                            $adId = $adOperation->getOperand()->getAd()->getId();
                            unset($deleteOperations[$adId]);
                        }
                    }
                } else {
                    throw new ErrorException('Empty result from google');
                }
            }

            $this->logger->info('Delete succeeded ads: ' . count($deleteOperations));
            foreach (array_chunk($deleteOperations, self::MAX_OPERATIONS_SIZE) as $i => $deleteChunk) {
                $this->logger->info('Delete chunk #' . $i . '. Size: ' . count($deleteChunk));
                $try = 0;
                $maxTry = 5;
                $jobResults = null;
                while (true) {
                    try {
                        $jobResults = $this->runMutateJob($deleteChunk);
                        break;
                    } catch (AdWordsBatchJobCancelledException $exception) {
                        $try++;
                        if ($try === $maxTry) {
                            throw $exception;
                        }
                    }
                }
                if (is_array($jobResults)) {
                    $this->processJobResult($result, $jobResults);
                } else {
                    throw new ErrorException('Empty result from google');
                }
            }
        } else {
            $mutateResult = $this->adGroupAdService->mutate($addOperations);
            $this->processMutateResult(
                $result,
                $addOperations,
                $mutateResult->getValue(),
                $mutateResult->getPartialFailureErrors()
            );
            if (!$result->success) {
                foreach ($result->errors as $adOperationId => $errors) {
                    $adOperation = $addOperations[$adOperationId];
                    $adId = $adOperation->getOperand()->getAd()->getId();
                    unset($deleteOperations[$adId]);
                }
            }

            if (count($deleteOperations) > 0) {
                $this->logger->info('Delete succeeded ads: ' . count($deleteOperations));
                $try = 0;
                $maxTry = 5;
                $jobResults = null;
                while (true) {
                    try {
                        $jobResults = $this->adGroupAdService->mutate(array_values($deleteOperations));
                        break;
                    } catch (Throwable $exception) {
                        $try++;
                        if ($try === $maxTry) {
                            throw $exception;
                        }
                    }
                }
                if ($jobResults !== null) {
                    $this->processMutateResult(
                        $result,
                        array_values($deleteOperations),
                        $jobResults->getValue(),
                        $jobResults->getPartialFailureErrors()
                    );
                }
            }
        }


        $this->logger->info('Done');
        $this->clearCache();
        return $result;
    }

    /**
     * @param array  $ads
     * @param string $operator
     *
     * @return UpdateResult
     *
     * @throws ErrorException
     * @throws ApiException
     */
    public function mutate(array $ads, string $operator): UpdateResult
    {
        $operations = [];
        foreach ($ads as $ad) {
            if (!($ad instanceof Ad)) {
                throw new ErrorException("Ad must be instance od Ad");
            }

            $operation = new AdOperation();
            $operation->setOperand($ad);
            $operation->setOperator($operator);
            $operations[] = $operation;
        }

        $mutateResult = $this->adService->mutate($operations);
        $result = new UpdateResult();
        $this->processMutateResult(
            $result,
            $operations,
            $mutateResult->getValue(),
            $mutateResult->getPartialFailureErrors()
        );
        $this->logger->info('Done');
        $this->clearCache();
        return $result;
    }

    /**
     * @param Operand $operand
     *
     * @return AdGroupAd|mixed
     */
    protected function getOperandEntity(Operand $operand)
    {
        return $operand->getAdGroupAd();
    }
}
