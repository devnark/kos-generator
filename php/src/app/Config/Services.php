<?php

namespace Config;

use CodeIgniter\Config\BaseService;
use App\Repositories\MaterialRepository;
use App\Repositories\MaterialRepositoryInterface;

class Services extends BaseService
{
    // ... здесь существующие методы ...

    /**
     * Возвращает синглтон репозитория материалов
     */
    public static function materialRepository($getShared = true): MaterialRepositoryInterface
    {
        if ($getShared) {
            return static::getSharedInstance('materialRepository');
        }

        return new MaterialRepository();
    }

    public static function professionRepository($getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('professionRepository');
        }
        return new \App\Repositories\ProfessionRepository();
    }

}
