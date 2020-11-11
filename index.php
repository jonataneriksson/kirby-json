<?php

Kirby::plugin('jonataneriksson/json', [
  'options' => [
    'cache' => true
  ],
  'hooks' => [
    'site.update:after' => function () {
        kirby()->cache('jonataneriksson.json')->flush();
    },
    'page.update:after' => function () {
        kirby()->cache('jonataneriksson.json')->flush();
    },
    'page.changeSlug:after' => function () {
        kirby()->cache('jonataneriksson.json')->flush();
    },
    'page.changeTitle:after' => function () {
        kirby()->cache('jonataneriksson.json')->flush();
    },
    'page.changeStatus:after' => function () {
        kirby()->cache('jonataneriksson.json')->flush();
    },
    'page.changeNum:after' => function () {
        kirby()->cache('jonataneriksson.json')->flush();
    },
    'page.delete:after' => function () {
        kirby()->cache('jonataneriksson.json')->flush();
    },
    'file.delete:after' => function () {
        kirby()->cache('jonataneriksson.json')->flush();
    },
  ],
  'routes' => function ($kirby) {
    return [
      [
        'pattern' => 'json',
        'action'  => function () {

          $apiCache = kirby()->cache('jonataneriksson.json');
          $cacheName = get('language') . "/" . get('path');
          $apiData = $apiCache->get($cacheName);

          if ($apiData === null || get('debug') ) {

            //Setup the return object
            $json = (object)[];

            //Some GET variables are needed
            $timer = get('debug') ? microtime(true) : false;
            $language = get('language');

            /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */
            /* !Helpers */
            /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */

            //Field is string
            function fieldisstring($field)
            {
                try {
                    if('object' == gettype($field)){
                      return ('string' == gettype($field->value()));
                    } else {
                      return false;
                    }
                } catch (Exception $exception) {
                    return false;
                }
            }

            //Field is yaml
            function fieldisyaml($field)
            {
                try {
                  $field->value = str_replace('*','_', $field->value);
                  if('array' == gettype($field->yaml())){
                    if('array' == gettype($field->yaml()[0])){
                      //Let's search for spaces
                      if (preg_match("/^[a-z]+$/", key($field->yaml()[0]))) {
                        return true;
                      }
                    }
                  }
                  return false;
                } catch (Exception $exception) {
                  return false;
                }
            }

            /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */
            /* !Get field */
            /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */

            function getfield($field) {
              if (fieldisyaml($field)) {
                $current_field = getstructure($field); //$field->toStructure();
              } else {
                $current_field['value'] = $field->value;
                $current_field['kirbytext'] = kirbytext($field->value);
              }
              return $current_field;
            }

            /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */
            /* !Get field */
            /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */

            function checkkey($key) {
              if (substr($key, 0, 1) === '_') {
                return false;
              }
              if ($key === 'id') {
                return false;
              }
              return true;
            }

            /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */
            /* !Get Array */
            /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */

            function getarray($input) {
              $return = [];
              foreach($input as $key => $value):
                if ( gettype($value) == 'string' && checkkey($key) ) {
                  $return[$key]['value'] = $value;
                  $return[$key]['kirbytext'] = kirbytext($value);
                } elseif ( gettype($value) == 'array' && checkkey($key)  ) {
                  $return[$key] = getarray($value);
                } else {
                  //Heres id & _key & _uid
                  $return[$key] = $value;
                }
              endforeach;
              return $return;
            }

            /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */
            /* !Get structure */
            /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */

            function getstructure($input) {
              $return = [];
              foreach($input->toStructure() as $index => $structure):
                $return[] = getarray($structure->toArray());
              endforeach;
              return $return;
            }

            /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */
            /* !Get fields */
            /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */

            function getfields($page) {
              $contentitem = [];
              foreach($page->content(get('language'))->data() as $key => $field):
                $contentitem[$key] = getfield( $page->content()->get($key) );
              endforeach;
              return $contentitem;
            }

            /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */
            /* !Get page structure */
            /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */

            function getpagestructures($pages) {

              //Let's make the return array
              $pageitems = (array)[];

              $index = 0;

              //Loop through pages
              foreach($pages as $page):

                //Save page data to array
                $pageitems[$page->uid()] = getpagestructure($page, $index);

                $index++;

              endforeach;

              //Return pages array
              return $pageitems;

            }

            /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */
            /* !Get pages */
            /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */

            function getpages($pages) {

              //Let's make the return array
              $pageitems = (array)[];

              $index = 0;

              //Loop through pages
              foreach($pages as $page):

                //Save page data to array
                $pageitems[$page->uid()] = getpage($page, $index);

                $index++;

              endforeach;

              //Return pages array
              return $pageitems;
            }

            /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */
            /* !Extend page */
            /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */

            function extendpage($page, $pageitem) {
              $pageitem->files = ($page->hasFiles()) ? getfiles($page) : false;
              $pageitem->content = getfields($page);
              $pageitem->strings = (array)$page->content(get('language'))->toArray();
              $pageitem->template = (string)$page->intendedTemplate();
              $pageitem->folder = (string)$page->contentURL();
              $pageitem->extended = true;
              return $pageitem;
            }

            /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */
            /* !Get page */
            /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */

            function getpagestructure($page, $index = 0) {

              //Let's make the return object
              $pageitem = (object) '';
              $pageitem->uri = (string)$page->uri();
              $pageitem->url = (string)$page->url();
              $pageitem->uid = (string)$page->uid();
              $pageitem->visible = (string)$page->isListed();

              //Setup children
              if($page->hasChildren() && !get('structure')):
                $pageitem->children = getpagestructures($page->children());
              else:
                $pageitem->children = false;
              endif;

              //Extend page item
              if(get('path')==(string)$page->uri() || get('full')):
                  $pageitem = extendpage($page, $pageitem);
              elseif('portfolio'==(string)$page->uri()):
                      $pageitem = extendpage($page, $pageitem);
              else:
                  $pageitem->extended = false;
              endif;

              //Return page array
              return $pageitem;
            }

            /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */
            /* !Get page */
            /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */

            function getpage($page, $index = 0) {

              //Let's make the return object
              $pageitem = (object) '';
              $pageitem->uri = (string)$page->uri();
              $pageitem->url = (string)$page->url();
              $pageitem->uid = (string)$page->uid();
              $pageitem->visible = (string)$page->isListed();

              //Get strings only.
              if(get('language')) $pageitem->language = get('language');
              $pageitem->strings = (array)$page->content(get('language'))->toArray();

              //Setup children
              if($page->hasChildren() && !get('structure')):
                $pageitem->children = getpages($page->children());
              endif;

              //Extend page item
              //if(get('path')==(string)$page->uri() || get('full')):
                  $pageitem = extendpage($page, $pageitem);

                  //Add some meta
                  $pageitem->index = $index;
              //else:
              //    $pageitem->extended = false;
              //endif;

              //Return page array
              return $pageitem;
            }

            /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */
            /* !Get files */
            /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */

            function getfiles($page) {

              $index = 0;

              //Loop through files
              foreach($page->files()->sortBy('sort', 'asc') as $file):
                $fileitems[$file->filename()] = getfile($file);
                $fileitems[$file->filename()]['index'] = (string)$index;
                $index++;
              endforeach;

              //Return file array
              return $fileitems;
            }

            /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */
            /* !Get one file */
            /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */

            function getfile($file) {

              $fileitem = [];
              $fileitem['name'] = (string)$file->name();
              $fileitem['type'] = (string)$file->type();
              $fileitem['extension'] = (string)$file->extension();
              $fileitem['src'] = (string)$file->url();

              if($fileitem['type'] == 'image'):
                $fileitem['height'] = (string)$file->height();
                $fileitem['width'] = (string)$file->width();
                $fileitem['ratio'] = (string)round($file->ratio()*100)/100;
                $fileitem['orientation'] = (string)$file->orientation();
                $fileitem['thumbnails'] = getthumbnails($file);
              endif;

              if($fileitem['type'] == 'video'):
                $stillfromvideo = $file->thumb(['clip' => true, 'still' => true]);
                $fileitem['height'] = (string)$stillfromvideo->height();
                $fileitem['width'] = (string)$stillfromvideo->width();
                $fileitem['ratio'] = (string)round($stillfromvideo->ratio()*100)/100;
                $fileitem['orientation'] = (string)$stillfromvideo->orientation();
                //$fileitem['thumbnails'] = getthumbnails($file, ['clip' => true, 'still' => true]);
              endif;

              foreach($file->content(get('language'))->data() as $key => $value):
                $fileitem['meta'][$key] = $value;
              endforeach;

              return $fileitem;
            }

            /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */
            /* !Create thumbnails */
            /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */

            function getthumbnails($file) {
              $thumbnails = [];
              $widths = option('thumbs.widths');
              foreach($widths as $width):
                $id =  'w' . $width ;
                $thumbnails[$id] = (string)$file->thumb(['width' => $width])->url();
              endforeach;
              $placeholderfile = $file->thumb(['width' => 15, 'quality' => 40]);
              $placeholderfile->publish();
              $placeholderpath = Url::path($placeholderfile->url());
              $placeholderurl = kirby()->site()->url() . '/' . $placeholderpath;
              $placeholder = $placeholderfile->base64();
              $thumbnails['placeholder'] = $placeholder;
              $filename = $file->name();
              return $thumbnails;
            }

            /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */
            /* !The Return */
            /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */

            //Language
            $json->language = get('language');

            //Site
            $json->site = kirby()->site()->content(get('language'))->toArray();

            //Page
            if(get('path')):
              if($page = kirby()->site()->pages()->findByURI(get('path'))):
                $json->page = getpage($page);
              endif;
            endif;

            //Pages
            $json->pages = getpages(kirby()->site()->pages());

            //Timer
            if($timer) $json->loadtime = microtime(true) - $timer;

            //For debug
            //return json_encode($json);

            //Retrun from cache
            $apiCache->set($cacheName, json_encode($json), 30);
          }

          return Response::json($apiCache->get($cacheName));
        }
      ]
    ];
  }
]);
