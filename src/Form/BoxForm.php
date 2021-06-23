<?php
/**
 * Created by PhpStorm.
 * User: enno
 * Date: 10.09.15
 * Time: 15:43
 */

namespace Drupal\grid_element_trash\Form;


use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use grid_box;

class BoxForm extends ConfigFormBase
{
    protected function getEditableConfigNames()
    {
        return ["grid.trash"];
    }

    public function getFormId()
    {
        return "grid_element_trash_box_trash";
    }

    public function buildForm(array $form, FormStateInterface $form_state)
    {
        /**
         * get the grid storage
         */
        $storage=grid_get_storage();

        $lib=grid_get_library();
        $api=$lib->api;

        /**
         * content_types that are activated for grid
         */
        /** @var String[] $content_types */
        $content_types= \Drupal::config("grid.settings")->get("enabled_node_types");

        $options = array();
        foreach ($content_types as $content_type) {
            $options[$content_type] = $content_type;
        }

        $form=array();

        $form['info']=array(
            '#type'=>'markup',
            '#markup'=>'<div><small>'.t('All checked Boxes will be placed in Trash and won\'t be available in Grid anymore.').'</small></div>',
        );

        /**
         * look for all meta type boxes
         * @var [type]
         */
        /** @var grid_box[] $meta_boxes */
        $meta_boxes = $api->getMetaTypes();
        foreach ($meta_boxes as $meta_box) {
            $class = get_class($meta_box);
            $meta_form = array(
                '#type' => 'fieldset',
                '#collapsible' => true,
                '#collapsed' => true,
                '#title' => $class . t(' meta type Boxes'),
            );

            /**
             * get all available implementations of meta type box
             */
            /** @var grid_box $obj */
            $obj = new $class();
            $obj->storage = $storage;
            /** @var grid_box[] $searchresult */
            $searchresult = $obj->metaSearch($meta_box->metaSearchCriteria(), "");
            foreach ($searchresult as $key => $box) {
                if ($class == "grid_reference_box") {
                    /**
                     * reusable boxes
                     */
                    $meta_form['renderd_ref_box_' . $box->content->boxid] = array(
                        '#type' => 'markup',
                        '#markup' => "<hr>" . $box->render(true),
                    );
                    $trashed=array();
                    $boxes=$this->config("grid.trash")->get("trashed_reuse_boxes");
                    if(isset($boxes[$box->content->boxid]))
                    {
                        $trashed=$this->config("grid.trash")->get("trashed_reuse_boxes")[$box->content->boxid];
                    }
                    $meta_form['grid_element_trash_box_reused_' . $box->content->boxid . '_trashed'] = array(
                        '#type' => 'checkboxes',
                        '#options' => $options,
                        '#title' => $box->type(),
                        '#default_value' => $trashed
                    );

                } else {
                    /**
                     * all other box types
                     */
                    $type = $box->type();
                    $trashed=array();
                    $boxes=$this->config("grid.trash")->get("trashed_boxes");
                    if(isset($boxes[$type]))
                    {
                        $trashed=$this->config("grid.trash")->get("trashed_boxes")[$type];
                    }
                    $meta_form['grid_element_trash_box_' . $type . '_trashed'] = array(
                        '#type' => 'checkboxes',
                        '#options' => $options,
                        '#title' => $type,
                        '#default_value' => $trashed
                    );
                }

            }

            $form['trash_' . $class] = $meta_form;
        }


        return parent::buildForm($form, $form_state);
    }

    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $storage=grid_get_storage();
        $lib=grid_get_library();
        $api=$lib->api;
        
        $trash_reuse=$this->config("grid.trash")->get("trashed_reuse_boxes");
        $trash_boxes=$this->config("grid.trash")->get("trashed_boxes");
        /** @var grid_box[] $meta_boxes */
        $meta_boxes = $api->getMetaTypes();
        foreach ($meta_boxes as $meta_box)
        {
            $class = get_class($meta_box);
            $obj = new $class();
            $obj->storage = $storage;
            /** @var grid_box[] $searchresult */
            $searchresult = $obj->metaSearch($meta_box->metaSearchCriteria(), "");
            foreach($searchresult as $key=>$box)
            {
                if($class=="grid_reference_box")
                {
                    $trashed=$form_state->getValue('grid_element_trash_box_reused_' . $box->content->boxid . '_trashed');
                    $trash_reuse[$box->content->boxid]=$trashed;
                }
                else
                {
                    $type = $box->type();
                    $trashed=$form_state->getValue('grid_element_trash_box_' . $type . '_trashed');
                    $trash_boxes[$type]=$trashed;
                }
            }
        }
        $this->config("grid.trash")
            ->set("trashed_reuse_boxes",$trash_reuse)
            ->set("trashed_boxes",$trash_boxes)
            ->save();
        parent::submitForm($form, $form_state);
    }


}
