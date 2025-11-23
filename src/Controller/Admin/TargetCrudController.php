<?php

namespace App\Controller\Admin;

use App\Entity\Target;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Survos\CoreBundle\Controller\BaseCrudController;

class TargetCrudController extends BaseCrudController
{
    public static function getEntityFqcn(): string
    {
        return Target::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('targetLocale', 'locale');
        yield TextField::new('marking', 'marking');
        yield IntegerField::new('length');
        yield TextField::new('snippet');
        yield AssociationField::new('source');
    }

}
