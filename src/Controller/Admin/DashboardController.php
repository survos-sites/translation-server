<?php

namespace App\Controller\Admin;

use App\Entity\Source;
use App\Entity\Str;
use App\Entity\Target;
use App\Repository\SourceRepository;
use App\Repository\StrTranslationRepository;
use App\Repository\TargetRepository;
use App\Workflow\TargetWorkflowInterface;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Survos\LinguaBundle\Workflow\StrTrWorkflowInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

#[AdminDashboard(routePath: '/admin', routeName: self::EZ_ROUTE)]
class DashboardController extends AbstractDashboardController
{
    const EZ_ROUTE = 'admin';
    public function __construct(
        #[Autowire('%kernel.enabled_locales%')] private array $enabledLocales,
    )
    {

    }
    public function index(): Response
    {
        // in AppController
        return $this->redirectToRoute(self::EZ_ROUTE . '_app_charts');

    }

    public function configureAssets(): Assets
    {
        return Assets::new()
            ->addAssetMapperEntry('admin')  // Your main app.js entry
            ;
    }
    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('LinguaServer');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::linkToRoute('Charts', 'fas fa-list', self::EZ_ROUTE . '_app_charts');
         yield MenuItem::linkToCrud('Source', 'fas fa-list', Source::class);
         yield MenuItem::linkToCrud('Target', 'fas fa-list', Target::class);
    }

}
