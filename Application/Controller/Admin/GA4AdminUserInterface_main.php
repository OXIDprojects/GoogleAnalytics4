<?php

declare(strict_types=1);

namespace D3\GoogleAnalytics4\Application\Controller\Admin;

use D3\GoogleAnalytics4\Application\Model\Constants;
use D3\GoogleAnalytics4\Application\Model\ManagerHandler;
use D3\GoogleAnalytics4\Application\Model\ManagerTypes;
use OxidEsales\Eshop\Application\Controller\Admin\ModuleConfiguration;
use OxidEsales\Eshop\Core\Module\Module;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\Str;
use OxidEsales\Eshop\Core\ViewConfig;
use OxidEsales\EshopCommunity\Internal\Container\ContainerFactory;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\Bridge\ModuleConfigurationDaoBridge;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\Bridge\ModuleConfigurationDaoBridgeInterface;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Facade\ModuleSettingService;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Facade\ModuleSettingServiceInterface;

class GA4AdminUserInterface_main extends \OxidEsales\Eshop\Application\Controller\Admin\AdminDetailsController
{
    protected $_sThisTemplate = '@' . Constants::OXID_MODULE_ID . '/admin/d3ga4uimain';

    public function render()
    {
        $return = parent::render();

        $this->addTplParam('d3ViewObject', $this);
        $this->addTplParam('d3ViewConfObject', Registry::get(ViewConfig::class));
        $this->addTplParam('d3ManagerTypeArray', oxNew(ManagerTypes::class)->getManagerList());
        $this->addTplParam('d3CurrentCMP', oxNew(ManagerHandler::class)->getActManager());

        $tmpArray = [];

        try {
            $moduleConfiguration = ContainerFactory::getInstance()
                ->getContainer()
                ->get(ModuleConfigurationDaoBridgeInterface::class)
                ->get(Constants::OXID_MODULE_ID);

            if (!empty($moduleConfiguration->getModuleSettings())) {
                $formatModuleSettings = $this
                    ->d3FormatModuleSettingsForTemplate($moduleConfiguration->getModuleSettings());

                $tmpArray["var_constraints"] = $formatModuleSettings['constraints'];
                $tmpArray["var_grouping"] = $formatModuleSettings['grouping'];

                foreach ($this->_aConfParams as $sType => $sParam) {
                    $tmpArray[$sParam] = $formatModuleSettings['vars'][$sType] ?? null;
                }
            }
        } catch (\Throwable $throwable) {
            Registry::getUtilsView()->addErrorToDisplay($throwable);
            Registry::getLogger()->error($throwable->getMessage());
        }

        $module = oxNew(Module::class);
        $module->load(Constants::OXID_MODULE_ID);

        dumpVar($module->getModuleData()['settings']);
        echo "<hr><hr><hr><hr><hr><hr>";
        dumpVar($tmpArray);
        die;

        return $return;
    }

    private function d3FormatModuleSettingsForTemplate(array $moduleSettings): array
    {
        $confVars = [
            'bool'     => [],
            'str'      => [],
            'arr'      => [],
            'aarr'     => [],
            'select'   => [],
            'password' => [],
        ];
        $constraints = [];
        $grouping = [];

        foreach ($moduleSettings as $setting) {
            $name = $setting->getName();
            $valueType = $setting->getType();
            $value = null;

            if ($setting->getValue() !== null) {
                switch ($setting->getType()) {
                    case 'arr':
                        $value = $this->arrayToMultiline($setting->getValue());
                        break;
                    case 'aarr':
                        $value = $this->aarrayToMultiline($setting->getValue());
                        break;
                    case 'bool':
                        $value = filter_var($setting->getValue(), FILTER_VALIDATE_BOOLEAN);
                        break;
                    default:
                        $value = $setting->getValue();
                        break;
                }
                $value = Str::getStr()->htmlentities($value);
            }

            $group = $setting->getGroupName();


            $confVars[$valueType][$name] = $value;
            $constraints[$name] = $setting->getConstraints() ?? '';

            if ($group) {
                if (!isset($grouping[$group])) {
                    $grouping[$group] = [$name => $valueType];
                } else {
                    $grouping[$group][$name] = $valueType;
                }
            }
        }

        return [
            'vars'        => $confVars,
            'constraints' => $constraints,
            'grouping'    => $grouping,
        ];
    }

    public function save()
    {
        parent::save();

        $aParams = Registry::getRequest()->getRequestEscapedParameter('editval');

        $aCheckBoxParams = [
            '_blEnableGa4',
            '_blEnableDebug',
            '_blEnableConsentMode',
            '_blEnableOwnCookieManager',
            '_blUseRealCategoyTitles',
            '_blEnableMeasurementCapabilities',
            '_blEnableUsercentricsConsentModeApi',
            '_blViewItemAddVariants',
        ];

        foreach ($aCheckBoxParams as $checkBoxName){
            if (isset($aParams['bool'][$checkBoxName])){
                $aParams['bool'][$checkBoxName] = true;
            }else{
                $aParams['bool'][$checkBoxName] = false;
            }
        }

        $this->d3SaveShopConfigVars($aParams);
    }

    /**
     * @return ModuleSettingService
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function d3GetModuleSettings() :ModuleSettingService
    {
        return ContainerFactory::getInstance()
            ->getContainer()
            ->get(ModuleSettingServiceInterface::class);
    }

    /**
     * @param string $sSettingName
     * @return bool
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function d3SettingExists(string $sSettingName) :bool
    {
        return $this->d3GetModuleSettings()
            ->exists(Constants::OXID_MODULE_ID.$sSettingName, Constants::OXID_MODULE_ID);
    }

    /**
     * @param array $aParams
     * @return void
     */
    protected function d3SaveShopConfigVars(array $aParams)
    {
        foreach ($aParams as $sConfigType => $aConfigParams) {
            foreach ($aConfigParams as $sSettingName => $sSettingValue){
                try {
                    //if($this->d3GetModuleConfigParam($sSettingName) !== $sSettingValue){}
                    if ($this->d3SettingExists($sSettingName)){
                        $sSettingName = Constants::OXID_MODULE_ID.$sSettingName;

                        // converting select to str
                        if ($sConfigType === "select"){
                            $sConfigType = "str";
                        }

                        switch ($sConfigType){
                           case 'str':
                               $this->d3GetModuleSettings()->saveString($sSettingName, $sSettingValue,Constants::OXID_MODULE_ID);
                               break;
                           case 'bool':
                               $this->d3GetModuleSettings()->saveBoolean($sSettingName, $sSettingValue,Constants::OXID_MODULE_ID);
                               break;
                           default:
                               Registry::getLogger()->error(
                                   'No given datatype defined!',
                                   [Constants::OXID_MODULE_ID." -> ".__METHOD__.": ".__LINE__." with '".$sSettingName."'"]
                               );
                       }
                }
                } catch (\Throwable $throwable) {
                    Registry::getUtilsView()->addErrorToDisplay($throwable);
                    Registry::getLogger()->error($throwable->getMessage());
                }

            }
        }
    }

    /**
     * @param string $configParamName
     * @return mixed
     */
    public function d3GetModuleConfigParam(string $configParamName)
    {
        $container = ContainerFactory::getInstance()->getContainer();
        $moduleConfiguration = $container->get(ModuleConfigurationDaoBridgeInterface::class)->get($moduleId);



        return Registry::get(ViewConfig::class)->d3GetModuleConfigParam($configParamName);
    }

    private function d3convertSettingsToArray(\OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\DataObject\ModuleConfiguration $moduleConfiguration): array
    {
        $data = [];

        foreach ($moduleConfiguration->getModuleSettings() as $index => $setting) {
            if ($setting->getGroupName()) {
                $data[$index]['group'] = $setting->getGroupName();
            }

            if ($setting->getName()) {
                $data[$index]['name'] = $setting->getName();
            }

            if ($setting->getType()) {
                $data[$index]['type'] = $setting->getType();
            }

            $data[$index]['value'] = $setting->getValue();

            if (!empty($setting->getConstraints())) {
                $data[$index]['constraints'] = $setting->getConstraints();
            }

            if ($setting->getPositionInGroup() > 0) {
                $data[$index]['position'] = $setting->getPositionInGroup();
            }
        }

        return $data;
    }
}