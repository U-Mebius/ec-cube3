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


namespace Eccube\Tests\Web\Admin\Product;

use Eccube\Entity\Category;
use Eccube\Repository\CategoryRepository;
use Eccube\Tests\Web\Admin\AbstractAdminWebTestCase;

class CategoryControllerTest extends AbstractAdminWebTestCase
{
    /**
     * @var CategoryRepository
     */
    protected $categoryRepository;

    public function setUp()
    {
        parent::setUp();

        $this->remove();
        $this->createCategories();
        $this->client->disableReboot();
        $this->categoryRepository = $this->container->get(CategoryRepository::class);
    }

    public function createCategories()
    {
        $categories = array(
            array('name' => '親1', 'hierarchy' => 1, 'sort_no' => 1,
                  'child' => array(
                      array('name' => '子1', 'hierarchy' => 2, 'sort_no' => 4,
                            'child' => array(
                                array('name' => '孫1', 'hierarchy' => 3, 'sort_no' => 9)
                            ),
                      ),
                  ),
            ),
            array('name' => '親2', 'hierarchy' => 1, 'sort_no' => 2,
                  'child' => array(
                      array('name' => '子2-0', 'hierarchy' => 2, 'sort_no' => 5,
                            'child' => array(
                                array('name' => '孫2', 'hierarchy' => 3, 'sort_no' => 10)
                            )
                      ),
                      array('name' => '子2-1', 'hierarchy' => 2, 'sort_no' => 6),
                      array('name' => '子2-2', 'hierarchy' => 2, 'sort_no' => 7)
                  ),
            ),
            array('name' => '親3', 'hierarchy' => 1, 'sort_no' => 3,
                  'child' => array(
                      array('name' => '子3', 'hierarchy' => 2, 'sort_no' => 8,
                            'child' => array(
                                array('name' => '孫3', 'hierarchy' => 3, 'sort_no' => 11)
                            )
                      )
                  ),
            ),
        );

        foreach ($categories as $category_array) {
            $Category = new Category();
            $Category->setPropertiesFromArray($category_array);
            $Category->setCreateDate(new \DateTime());
            $Category->setUpdateDate(new \DateTime());
            $this->entityManager->persist($Category);
            $this->entityManager->flush();

            if (!array_key_exists('child', $category_array)) {
                continue;
            }
            foreach ($category_array['child'] as $child_array) {
                $Child = new Category();
                $Child->setPropertiesFromArray($child_array);
                $Child->setParent($Category);
                $Child->setCreateDate(new \DateTime());
                $Child->setUpdateDate(new \DateTime());
                $this->entityManager->persist($Child);
                $this->entityManager->flush();
                // add child category
                $Category->addChild($Child);

                if (!array_key_exists('child', $child_array)) {
                    continue;
                }
                foreach ($child_array['child'] as $grandson_array) {
                    $Grandson = new Category();
                    $Grandson->setPropertiesFromArray($grandson_array);
                    $Grandson->setParent($Child);
                    $Grandson->setCreateDate(new \DateTime());
                    $Grandson->setUpdateDate(new \DateTime());
                    $this->entityManager->persist($Grandson);
                    $this->entityManager->flush();
                    // add child category
                    $Child->addChild($Grandson);
                }
            }
        }
    }

    /**
     * 既存のデータを削除しておく.
     */
    public function remove() {
        $this->deleteAllRows([
            'dtb_product_category',
            'dtb_category'
        ]);
    }

    public function testRoutingAdminProductCategory()
    {
        $this->client->request('GET',
            $this->generateUrl('admin_product_category')
        );

        $this->assertTrue($this->client->getResponse()->isSuccessful());
    }

    public function testIndexWithPost()
    {
        $params = array(
            '_token' => 'dummy',
            'name' => 'テストカテゴリ'
        );

        $this->client->request(
            'POST',
            $this->generateUrl('admin_product_category'),
            array('admin_category' => $params)
        );

        $this->assertTrue($this->client->getResponse()->isRedirect($this->generateUrl('admin_product_category')));
    }

    public function testIndexWithPostParent()
    {
        $params = array(
            '_token' => 'dummy',
            'name' => 'テストカテゴリ'
        );
        $Parent = $this->categoryRepository->findOneBy(array('name' => '子1'));
        $this->client->request(
            'POST',
            $this->generateUrl('admin_product_category_show', array('parent_id' => $Parent->getId())),
            array('admin_category' => $params)
        );
        $url = $this->generateUrl('admin_product_category_show', array('parent_id' => $Parent->getId()));
        $this->assertTrue($this->client->getResponse()->isRedirect($url));
    }

    public function testRoutingAdminProductCategoryShow()
    {
        // before
        $TestCreator = $this->createMember();
        $TestParentCategory = $this->newTestCategory($TestCreator);
        $this->entityManager->persist($TestParentCategory);
        $this->entityManager->flush();
        $TestCategory = $this->newTestCategory($TestCreator, $TestParentCategory);
        $this->entityManager->persist($TestCategory);
        $this->entityManager->flush();

        $test_parent_category_id = $this->categoryRepository
            ->findOneBy(array(
                'name' => $TestParentCategory->getName()
            ))
            ->getId();

        // main
        $this->client->request('GET',
            $this->generateUrl('admin_product_category_show',
                array('parent_id' => $test_parent_category_id))
        );
        $this->assertTrue($this->client->getResponse()->isSuccessful());
    }

    public function testRoutingAdminProductCategoryEdit()
    {
        // before
        $TestCreator = $this->createMember();
        $TestCategory = $this->newTestCategory($TestCreator);
        $this->entityManager->persist($TestCategory);
        $this->entityManager->flush();
        $test_category_id = $this->categoryRepository
            ->findOneBy(array(
                'name' => $TestCategory->getName()
            ))
            ->getId();

        // main
        $this->client->request('GET',
            $this->generateUrl('admin_product_category_edit',
                array('id' => $test_category_id))
        );
        $this->assertTrue($this->client->getResponse()->isSuccessful());
    }

    public function testRoutingAdminProductCategoryDelete()
    {
        // before
        $TestCreator = $this->createMember();
        $TestCategory = $this->newTestCategory($TestCreator);
        $this->entityManager->persist($TestCategory);
        $this->entityManager->flush();
        $test_category_id = $this->categoryRepository
            ->findOneBy(array(
                'name' => $TestCategory->getName()
            ))
            ->getId();

        // main
        $redirectUrl = $this->generateUrl('admin_product_category');
        $this->client->request('DELETE',
            $this->generateUrl('admin_product_category_delete',
                array('id' => $test_category_id)),
            array('_token' => 'dummy')
        );

        $this->assertTrue($this->client->getResponse()->isRedirect($redirectUrl));
    }

    public function testMoveSortNo()
    {
        $Category = $this->categoryRepository->findOneBy(array('name' => '子1'));

        $this->client->request(
            'POST',
            $this->generateUrl('admin_product_category_sort_no_move'),
            array($Category->getId() => 10),
            array(),
            array(
                'HTTP_X-Requested-With' => 'XMLHttpRequest',
                'CONTENT_TYPE' => 'application/json',
            )
        );
        $this->assertTrue($this->client->getResponse()->isSuccessful());

        $MovedCategory = $this->categoryRepository->find($Category->getId());
        $this->expected = 10;
        $this->actual = $MovedCategory->getSortNo();
        $this->verify();
    }

    public function testExport()
    {
        // 2-0 という文字列が含まれる CSV が出力されるか
        $this->expectOutputRegex('/2-0/');

        $this->client->request('GET',
            $this->generateUrl('admin_product_category_export')
        );
        $this->assertTrue($this->client->getResponse()->isSuccessful());
    }

    private function newTestCategory($TestCreator, $TestParentCategory = null)
    {
        $TestCategory = new \Eccube\Entity\Category();
        if ($TestParentCategory == null) {
            $TestCategory->setName('テスト家具')
                ->setSortNo(100)
                ->setHierarchy(100)
                ->setParent($TestParentCategory)
                ->setCreator($TestCreator);
        } else {
            $TestCategory->setName($TestParentCategory->getName() . '_c')
                ->setSortNo($TestParentCategory->getSortNo() + 1)
                ->setHierarchy($TestParentCategory->getHierarchy() + 1)
                ->setParent($TestParentCategory)
                ->setCreator($TestCreator);
        }

        return $TestCategory;
    }

    public function testMoveSortNoAndShow()
    {
        // Give
        $Category = $this->categoryRepository->findOneBy(array('name' => '親1'));
        $Category2 = $this->categoryRepository->findOneBy(array('name' => '親2'));
        $newSortNos = array(
            $Category->getId() => $Category2->getSortNo(),
            $Category2->getId() => $Category->getSortNo()
        );

        // When
        $this->client->request(
            'POST',
            $this->generateUrl('admin_product_category_sort_no_move'),
            $newSortNos,
            array(),
            array(
                'HTTP_X-Requested-With' => 'XMLHttpRequest',
                'CONTENT_TYPE' => 'application/json',
            )
        );

        // Then
        $this->assertTrue($this->client->getResponse()->isSuccessful());

        $this->expected = $newSortNos[$Category->getId()];
        $this->actual = $Category->getSortNo();
        $this->verify();

        $crawler = $this->client->request('GET',
            $this->generateUrl('admin_product_product_new')
        );

        $CategoryLast = $this->categoryRepository->findOneBy(array('name' => '子2-2'));
        $categoryNameLastElement = $crawler->filter('#detail_wrap select#admin_product_Category option')->last()->text();

        $this->expected = $CategoryLast->getNameWithLevel();
        $this->actual = $categoryNameLastElement;
        $this->verify();
    }
}
