<?php

namespace Application;

use Application\Service\Factory\FileStorageFactory as FileStorageServiceFactory;
use Application\Service\FileStorage as FileStorageService;
use Application\View\Helper\{
    FileUrl,
    IsModuleActive,
};
use Laminas\I18n\Translator\Translator as I18nTranslator;
use Laminas\Mvc\ModuleRouteListener;
use Laminas\Mvc\MvcEvent;
use Laminas\Mvc\I18n\Translator as MvcTranslator;
use Laminas\Session\Container as SessionContainer;
use Laminas\Validator\AbstractValidator;
use Locale;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Psr\Container\ContainerInterface;

class Module
{
    public function onBootstrap(MvcEvent $e)
    {
        $eventManager        = $e->getApplication()->getEventManager();
        $moduleRouteListener = new ModuleRouteListener();
        $moduleRouteListener->attach($eventManager);

        $locale = $this->determineLocale($e);

        /** @var MvcTranslator $mvcTranslator */
        $mvcTranslator = $e->getApplication()->getServiceManager()->get(MvcTranslator::class);
        $translator = $mvcTranslator->getTranslator();
        if ($translator instanceof I18nTranslator) {
            $translator->setlocale($locale);
        }

        Locale::setDefault($locale);

        $eventManager->attach(MvcEvent::EVENT_DISPATCH_ERROR, [$this, 'logError']);
        $eventManager->attach(MvCEvent::EVENT_RENDER_ERROR, [$this, 'logError']);

        // Enable Laminas\Validator default translator
        AbstractValidator::setDefaultTranslator($mvcTranslator);
    }

    /**
     * @param MvcEvent $e
     */
    public function logError($e)
    {
        $container = $e->getApplication()->getServiceManager();
        $logger = $container->get('logger');

        if ('error-router-no-match' === $e->getError()) {
            // not an interesting error
            return;
        }
        if ('error-exception' === $e->getError()) {
            $logger->error($e->getParam('exception'));

            return;
        }

        $logger->error($e->getError());
    }

    protected function determineLocale(MvcEvent $e)
    {
        $session = new SessionContainer('lang');
        if (!isset($session->lang)) {
            $session->lang = 'en';
        }
        return $session->lang;
    }

    /**
     * Get the configuration for this module.
     *
     * @return array Module configuration
     */
    public function getConfig(): array
    {
        return include __DIR__ . '/../config/module.config.php';
    }

    /**
     * Get service configuration.
     *
     * @return array Service configuration
     */
    public function getServiceConfig(): array
    {
        return [
            'factories' => [
                FileStorageService::class => FileStorageServiceFactory::class,
                'logger' => function (ContainerInterface $container) {
                    $logger = new Logger('gewisdb');
                    $config = $container->get('config')['logging'];

                    $handler = new RotatingFileHandler(
                        $config['logfile_path'],
                        $config['max_rotate_file_count'],
                        $config['minimal_log_level'],
                    );
                    $logger->pushHandler($handler);

                    return $logger;
                },
            ],
        ];
    }

    /**
     * Get view helper configuration.
     *
     * @return array
     */
    public function getViewHelperConfig(): array
    {
        return [
            'factories' => [
                'fileUrl' => function (ContainerInterface $container) {
                    return new FileUrl($container->get('config'));
                },
                'isModuleActive' => function (ContainerInterface $container) {
                    return new IsModuleActive($container);
                },
            ],
        ];
    }
}
