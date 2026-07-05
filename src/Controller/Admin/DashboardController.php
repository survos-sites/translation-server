<?php

namespace App\Controller\Admin;

use App\Entity\Source;
use App\Entity\Target;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Registry\AdminControllerRegistry;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Survos\FieldBundle\Registry\EntityMetaRegistry;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: self::EZ_ROUTE)]
class DashboardController extends AbstractDashboardController
{
    const EZ_ROUTE = 'admin';
    public function __construct(
        #[Autowire('%kernel.enabled_locales%')] private array $enabledLocales,
        private readonly EntityManagerInterface $entityManager,
        private readonly EntityMetaRegistry $entityMetaRegistry,
        private readonly AdminControllerRegistry $adminControllerRegistry,
        private readonly AdminUrlGenerator $adminUrlGenerator,
    )
    {

    }

    /**
     * The EasyAdmin "home" — a simple overview of the entities EasyAdmin manages.
     * General admin navigation (dashboard, charts, browse pages) lives in Tabler instead.
     */
    /**
     * The EasyAdmin "home" — a simple overview of the entities EasyAdmin manages.
     * General admin navigation (dashboard, charts, browse pages) lives in Tabler instead.
     *
     * Rendered outside EasyAdmin's own layout: a genuine Dashboard-only action (no CRUD
     * context) currently hangs when rendered through ez-bundle's layout chain — reproducible
     * even with zero custom content — so this stays on the same Tabler base as the rest of
     * the app's non-CRUD admin pages.
     */
    public function index(): Response
    {
        $entities = [];
        foreach ($this->entityMetaRegistry->getByGroup('Translation') as $entityMeta) {
            $crudControllerFqcn = $this->adminControllerRegistry->findCrudControllerByEntity($entityMeta->class);
            if ($crudControllerFqcn === null) {
                continue;
            }

            $entities[] = [
                'meta'  => $entityMeta,
                'count' => $this->entityManager->getRepository($entityMeta->class)->count([]),
                'url'   => $this->adminUrlGenerator->setController($crudControllerFqcn)->generateUrl(),
            ];
        }

        return $this->render('admin/index.html.twig', [
            'entities' => $entities,
        ]);
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
