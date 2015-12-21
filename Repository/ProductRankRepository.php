<?php
/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) 2000-2015 LOCKON CO.,LTD. All Rights Reserved.
 *
 * http://www.lockon.co.jp/
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 */

namespace Plugin\ProductRank\Repository;

use Doctrine\ORM\EntityRepository;
use Eccube\Entity\ProductCategory;
use Eccube\Entity\Category;

/**
 * ProductRank
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class ProductRankRepository extends EntityRepository
{
    /**
     * @var \Eccube\Application
     */
    private $app;

    /**
     * ProductRankRepository constructor.
     * @param \Doctrine\ORM\EntityManager $em
     * @param \Doctrine\ORM\Mapping\ClassMetadata $class
     * @param \Eccube\Application $app
     */
    public function __construct($em, \Doctrine\ORM\Mapping\ClassMetadata $class, \Eccube\Application $app)
    {
        parent::__construct($em, $class);
        $this->app = $app;
    }

    /**
     * @param \Eccube\Entity\Category $Category
     * @return \Eccube\Entity\ProductCategory
     */
    public function findBySearchData(\Eccube\Entity\Category $Category = null) {
        if (empty($Category) || !$Category) {
            return null;
        }
        $qb = $this->_em->getRepository('\Eccube\Entity\ProductCategory')
            ->createQueryBuilder('pc');
        $qb
            ->select('pc,p,c')
            ->innerJoin('pc.Product', 'p')
            ->innerJoin('pc.Category', 'c')
            ->where($qb->expr()->eq('pc.Category', ':Category'))
            ->setParameter('Category', $Category)
            ->orderBy('c.rank', 'DESC')
            ->addOrderBy('pc.rank', 'DESC')
            ->addOrderBy('p.id', 'DESC');
        return $qb->getQuery()->getResult();
    }

    /**
     * @param \Eccube\Entity\ProductCategory $TargetProductCategory
     * @return bool
     */
    public function up(ProductCategory $TargetProductCategory) {
        $this->_em->getConnection()->beginTransaction();
        try {
            $rank = $TargetProductCategory->getRank();

            /** @var \Eccube\Entity\ProductCategory $ProductCategoryUp */
            $ProductCategoryUp = $this->_em->getRepository('\Eccube\Entity\ProductCategory')
                ->createQueryBuilder('pc')
                ->where('pc.rank > :rank and pc.category_id = :category_id AND pc.product_id != :product_id')
                ->setParameter('rank', $rank)
                ->setParameter('category_id', $TargetProductCategory->getCategoryId())
                ->setParameter('product_id', $TargetProductCategory->getProductId())
                ->orderBy('pc.rank', 'ASC')
                ->setMaxResults(1)
                ->getQuery()
                ->getSingleResult();

            $TargetProductCategory->setRank($ProductCategoryUp->getRank());
            $ProductCategoryUp->setRank($rank);

            $this->_em->persist($TargetProductCategory);
            $this->_em->persist($ProductCategoryUp);

            $this->_em->flush();
            $this->_em->getConnection()->commit();

            return true;
        } catch (\Exception $e) {
            $this->_em->getConnection()->rollback();
            $this->_em->close();
            $this->app->log($e);
        }

        return false;
    }

    /**
     * @param \Eccube\Entity\ProductCategory $TargetProductCategory
     * @return bool
     */
    public function down(ProductCategory $TargetProductCategory) {

        $this->_em->getConnection()->beginTransaction();
        try {
            $rank = $TargetProductCategory->getRank();

            $ProductCategoryDown = $this->_em->getRepository('\Eccube\Entity\ProductCategory')
                ->createQueryBuilder('pc')
                ->where('pc.rank <= :rank and pc.category_id = :category_id AND pc.product_id != :product_id')
                ->setParameter('rank', $rank)
                ->setParameter('category_id', $TargetProductCategory->getCategoryId())
                ->setParameter('product_id', $TargetProductCategory->getProductId())
                ->orderBy('pc.rank', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->getSingleResult();

            $TargetProductCategory->setRank($ProductCategoryDown->getRank());
            $ProductCategoryDown->setRank($rank);

            $this->_em->persist($TargetProductCategory);
            $this->_em->persist($ProductCategoryDown);

            $this->_em->flush();
            $this->_em->getConnection()->commit();

            return true;
        } catch (\Exception $e) {
            $this->_em->getConnection()->rollback();
            $this->_em->close();
            $this->app->log($e);
        }

        return false;
    }

    /**
     * @param Category $Category
     * @return bool
     * @throws \Doctrine\DBAL\ConnectionException
     */
    public function renumber(Category $Category)
    {
        $this->_em->getConnection()->beginTransaction();
        try {
            $ProductCategories = $this->findBySearchData($Category);

            $maxRank = count($ProductCategories);
            $rank = $maxRank;
            foreach ($ProductCategories as $ProductCategory) {
                /* @var $ProductCategory \Eccube\Entity\ProduCategory */
                $ProductCategory = $this->_em->getRepository('\Eccube\Entity\ProductCategory')
                    ->findOneBy(array('category_id' => $ProductCategory->getCategoryId(), 'product_id' => $ProductCategory->getProductId()));
                $ProductCategory->setRank($rank);
                $this->_em->persist($ProductCategory);
                $rank--;
            }
            $this->_em->flush();

            $this->_em->getConnection()->commit();

            return true;
        } catch (\Exception $e) {
            $this->_em->getConnection()->rollback();
            $this->_em->close();
            $this->app->log($e);
        }

        return false;
    }

    /**
     * @param ProductCategory $TargetProductCategory
     * @param $position
     * @return bool
     * @throws \Doctrine\DBAL\ConnectionException
     */
    public function moveRank(ProductCategory $TargetProductCategory, $position) {
        $repos = $this->_em->getRepository('\Eccube\Entity\ProductCategory');

        $this->_em->getConnection()->beginTransaction();
        try {
            $oldRank = $TargetProductCategory->getRank();

            // 最大値取得
            $qb = $repos->createQueryBuilder('pc');
            $max = $qb
                ->select($qb->expr()->max('pc.rank'))
                ->where($qb->expr()->eq('pc.category_id', $TargetProductCategory->getCategoryId()))
                ->getQuery()
                ->getSingleScalarResult();

            $position = $max - ($position - 1);
            $position = max(1, $position);
            $TargetProductCategory->setRank($position);
            $status = true;
            if ($position != $oldRank) {
                // 他のItemのランクを調整する
                if ($position < $oldRank) {
                    // down
                    $this->_em->createQueryBuilder()
                        ->update('\Eccube\Entity\ProductCategory', 'pc')
                        ->set('pc.rank', 'pc.rank + 1')
                        ->where('pc.rank <= :oldRank AND pc.rank >= :rank AND pc.category_id = :category_id AND pc.product_id != :product_id')
                        ->setParameter('oldRank', $oldRank)
                        ->setParameter('rank', $position)
                        ->setParameter('category_id', $TargetProductCategory->getCategoryId())
                        ->setParameter('product_id', $TargetProductCategory->getProductId())
                        ->getQuery()
                        ->execute();
                } else {
                    // up
                    $this->_em->createQueryBuilder()
                        ->update('\Eccube\Entity\ProductCategory', 'pc')
                        ->set('pc.rank', 'pc.rank - 1')
                        ->where('pc.rank >= :oldRank AND pc.rank <= :rank AND pc.category_id = :category_id AND pc.product_id != :product_id')
                        ->setParameter('oldRank', $oldRank)
                        ->setParameter('rank', $position)
                        ->setParameter('category_id', $TargetProductCategory->getCategoryId())
                        ->setParameter('product_id', $TargetProductCategory->getProductId())
                        ->getQuery()
                        ->execute();
                }
                $this->_em->persist($TargetProductCategory);

                $this->_em->flush();
            }
            $this->_em->getConnection()->commit();

            return $status;
        } catch (\Exception $e) {
            $this->_em->getConnection()->rollback();
            $this->_em->close();
            $this->app->log($e);
        }

        return false;
    }
}
