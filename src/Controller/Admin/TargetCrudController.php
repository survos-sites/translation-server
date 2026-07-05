<?php

namespace App\Controller\Admin;

use App\Entity\Target;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class TargetCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Target::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('key', 'Key');
        yield AssociationField::new('source');
        yield TextField::new('targetLocale');
        yield TextField::new('engine');
        yield TextField::new('marking');
        yield TextField::new('snippet');
        yield IntegerField::new('length');
    }
}
