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
          //$apiData = null;

          if ($apiData === null || get('debug') ) {

            //Setup the return object
            $json = (object)[];

            //Some GET variables are needed
            $timer = microtime(true);
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

            //String is JSON
            function stringIsJson($string) {
              if(is_numeric($string)) return false;
              json_decode((string)$string);
              return (json_last_error() == JSON_ERROR_NONE);
            }

            /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */
            /* !Get field */
            /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */

            function getfield($field) {
              $return = [];
              if (fieldisyaml($field)) {
                $return = getstructure($field);
              } elseif(stringIsJson($field->value)) {
                $jsonvalue = json_decode($field->value);
                if ( gettype($jsonvalue) == 'array' ) {
                  $return[$field->key()] = getarray(json_decode($field->value));
                } elseif(gettype($jsonvalue) == 'string' ) {
                  $return[$field->key()] = $jsonvalue;
                }
              } else {
                $return['value'] = $field->value;
                $return['kirbytext'] = kirbytext($field->value);
              }
              return $return;
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
                if(gettype($value) == 'bool') {
                  print_r('NULL!');
                }
                if ( (gettype($value) == 'string' || gettype($value) == 'double') && checkkey($key) ) {
                  if(stringIsJson($value)) {
                    $jsonvalue = json_decode($value);
                    if ( gettype($jsonvalue) == 'array' ) {
                      $return[$key] = getarray(json_decode($value));
                    } elseif(gettype($jsonvalue) == 'string' ) {
                      $return[$key] = $jsonvalue;
                    }
                  } else {
                    $return[$key]['value'] = $value;
                    $return[$key]['kirbytext'] = kirbytext($value);
                  }
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
              foreach($input->toStructure() as $fieldgroup):
                $return[] = getarray($fieldgroup->toArray());
              endforeach;
              return $return;
            }

            /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */
            /* !Get fields */
            /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */

            function getfields($page) {
              $contentitem = [];
              foreach($page->content(get('language'))->data() as $key => $field):
                $contentitem[$key] = getfield( $page->content(get('language'))->get($key) );
              endforeach;
              return $contentitem;
            }

            /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */
            /* !Get page structure */
            /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */

            function getpagestructures($pages) {

              //Let's make the return array
              $pageitems = false;
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
              $pageitem->children = getpagestructures($page->children());
              $pageitem->extended = true;
              $pageitem->index = (int) $page->num();
              $pageitem->next = nextOrFirstListedSibling($page);
              $pageitem->prev = prevOrLastListedSibling($page);
              return $pageitem;
            }

            /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */
            /* !Next and Prev?*/
            /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */

            function nextOrFirstListedSibling($page) {
              $collection = $page->siblings()->listed();
              if ($page->nextListed($collection)) {
                return $page->nextListed($collection)->uri();
              } elseif($collection->first()) {
                return $collection->first()->uri();
              } else {
                return false;
              }
            }

            function prevOrLastListedSibling($page) {
              $collection = $page->siblings()->listed();
              if ($page->prevListed($collection) ) {
                return $page->prevListed($collection)->uri();
              } elseif($collection->last()) {
                return $collection->last()->uri();
              } else {
                return false;
              }
            }

            /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */
            /* !Should parent be extended?*/
            /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */

            function loadparent($page) {
              if ($page->children()) {
                foreach($page->children() as $child):
                  if( get('path') == (string)$child->uri() ):
                    return true;
                  endif;
                endforeach;
              }
              return false;
            }

            /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */
            /* !Should children be extended?*/
            /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */

            function loadchildren($page) {
              if ($page->parent()) {
                if( get('path') == $page->parent()->uri() ):
                  return true;
                endif;
              }
              return false;
            }

            /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */
            /* !Should page be extended?*/
            /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */

            function shouldbeextended($page) {
              if( get('path')==(string)$page->uri() ):
                return true;
              elseif( get('full') ):
                return true;
              elseif( loadparent($page) ):
                return true;
              elseif( loadchildren($page) ):
                return true;
              endif;

              if( get('require') ):
                $requiredpages = explode(',',get('require'));
                foreach($requiredpages as $requiredpage):
                  if( $requiredpage == (string)$page->uri() ):
                    return true;
                  endif;
                endforeach;
              endif;
              return false;
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
              $pageitem->strings = (array)$page->content(get('language'))->toArray();

              //Setup children
              if($page->hasChildren()):
                $pageitem->children = getpagestructures($page->children());
              else:
                $pageitem->children = false;
              endif;

              //Extend page item
              if(shouldbeextended($page)):
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
              $pageitem->parent = (string)$page->parent();
              $pageitem->visible = (string)$page->isListed();
              $pageitem->index = $index;

              //Get strings only.
              if(get('language')) $pageitem->language = get('language');
              $pageitem->strings = (array)$page->content(get('language'))->toArray();

              //Setup children
              if( ($page->hasChildren() && get('structure')==1) || (get('full')==1) ):
                $pageitem->children = getpages($page->children());
              else:
                $pageitem->children = false;
              endif;

              //Extend page item
              if(shouldbeextended($page)):
                  $pageitem = extendpage($page, $pageitem);
              else:
                  $pageitem->extended = false;
              endif;

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
                //$stillfromvideo = $file->thumb(['clip' => true, 'still' => true]);
                //$fileitem['height'] = (string)$stillfromvideo->height();
                //$fileitem['width'] = (string)$stillfromvideo->width();
                //$fileitem['ratio'] = (string)round($stillfromvideo->ratio()*100)/100;
                //$fileitem['orientation'] = (string)$stillfromvideo->orientation();
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
              //$placeholderfile = $file->thumb(['width' => 15, 'quality' => 40]);
              //$placeholderfile->publish();
              //$placeholderpath = Url::path($placeholderfile->url());
              //$placeholderurl = kirby()->site()->url() . '/' . $placeholderpath;
              //$placeholder = $placeholderfile->base64();
              //$thumbnails['placeholder'] = $placeholder;
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
            $json->loadtime = microtime(true) - $timer;

            //Retrun from cache
            $apiCache->set($cacheName, json_encode($json), 30);
          }

          return Response::json($apiCache->get($cacheName));
        }
      ]
    ];
  }
]);
