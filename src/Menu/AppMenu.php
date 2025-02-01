<?php

namespace App\Menu;

use App\Entity\Target;
use Survos\BootstrapBundle\Event\KnpMenuEvent;
use Survos\BootstrapBundle\Service\MenuService;
use Survos\BootstrapBundle\Traits\KnpMenuHelperInterface;
use Survos\BootstrapBundle\Traits\KnpMenuHelperTrait;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

// events are
/*
// #[AsEventListener(event: KnpMenuEvent::NAVBAR_MENU2)]
#[AsEventListener(event: KnpMenuEvent::SIDEBAR_MENU, method: 'sidebarMenu')]
#[AsEventListener(event: KnpMenuEvent::PAGE_MENU, method: 'pageMenu')]
#[AsEventListener(event: KnpMenuEvent::FOOTER_MENU, method: 'footerMenu')]
#[AsEventListener(event: KnpMenuEvent::AUTH_MENU, method: 'appAuthMenu')]
*/

final class AppMenu implements KnpMenuHelperInterface
{
    use KnpMenuHelperTrait;

    public function __construct(
        #[Autowire('%kernel.environment%')] protected string $env,
        #[Autowire('%kernel.enabled_locales%')] private array $enabled_locales,
        private MenuService $menuService,
        private Security $security,
        private ?AuthorizationCheckerInterface $authorizationChecker = null
    ) {
    }

    public function appAuthMenu(KnpMenuEvent $event): void
    {
        $menu = $event->getMenu();
        $this->menuService->addAuthMenu($menu);
    }

    #[AsEventListener(event: KnpMenuEvent::NAVBAR_MENU)]
    public function navbarMenu(KnpMenuEvent $event): void
    {
        $menu = $event->getMenu();
        $options = $event->getOptions();

        $this->add($menu, 'app_homepage');
        // for nested menus, don't add a route, just a label, then use it for the argument to addMenuItem

        $nestedMenu = $this->addSubmenu($menu, 'Test Source');
        foreach ($this->enabled_locales as $locale) {
            $this->add($nestedMenu, 'app_test_api', ['locale' => $locale], label: $locale);
        }

        $nestedMenu = $this->addSubmenu($menu, 'Target By Marking');
        foreach (Target::PLACES as $place) {
            $this->add($nestedMenu, 'app_browse_target', ['marking' => $place], label: $place);
        }
        $this->add($nestedMenu, 'app_browse_target', label: 'All');

        $nestedMenu = $this->addSubmenu($menu, 'Commands');
        $this->add($nestedMenu, 'survos_commands', label: 'all');
        foreach (['app:dispatch','app:export','app:import'] as $command) {
            $this->add($nestedMenu, 'survos_command', ['commandName' => $command], label: $command);

        }



    }
}
