<?php

namespace App\Controller\Admin;

use App\Entity\Source;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Survos\EzBundle\Controller\BaseCrudController;

class SourceCrudController extends BaseCrudController
{
    public static function getEntityFqcn(): string
    {
        return Source::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID');
        yield TextField::new('locale');
        yield IntegerField::new('length');
        yield TextField::new('snippet');
        yield TextField::new('hash');
        yield AssociationField::new('targets');
    }
}
