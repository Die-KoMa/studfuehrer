<?php
require 'vendor/autoload.php';
require 'model.php';
require 'config.php';

class GlobalFiltersExtension extends \Twig_Extension {
    public function getGlobals() {
        $questions = Question::where_not_equal('type', Question::TYPE_FREETEXT)->findMany();
        return array(
            'filter_questions' => $questions,
        );
    }
    public function getName() {
        return 'GlobalFilters_extension';
    }
}

class JoinAttributeExtension extends \Twig_Extension {
    public function getFilters() {
        return array(
            new Twig_SimpleFilter('join_attr', function($objs, $attr, $glue = '') {
                if(!is_array($objs) || count($objs) == 0)
                    return "";
                array_walk($objs, function(&$obj, $idx, $attr) {
                    $obj = $obj->$attr;
                }, $attr);
                return implode($glue, $objs);
            })
        );
    }
    public function getName() {
        return 'JoinAttributeExtension';
    }
}

// Start Slim with Twig
$app = new \Slim\Slim(array(
   'view' => new \Slim\Views\Twig()
));

$view = $app->view();
$view->parserExtensions = array(
    new \Slim\Views\TwigExtension(),
    new Twig_Extension_Escaper('html'),
    new GlobalFiltersExtension(),
    new JoinAttributeExtension(),
);

$app->notFound(function() use ($app) {
    $app->render('notfound.html');
});

$app->get('/', function() use ($app) {
    $app->render('index.html');
});

$app->map('/filter', function() use ($app) {
    $filter_questions = Question::where_not_equal('type', Question::TYPE_FREETEXT)->findMany();
    $universities = Model::factory('University');
    foreach($filter_questions as $question) {
        $filterval = $app->request->params('q' . $question->id);
        if(!empty($filterval)) {
            $universities = $universities->inner_join('answer', 'q' . $question->id . '.university_id = university.id AND q' . $question->id . '.question_id = ' . $question->id, 'q' . $question->id);
            switch($question->type) {
                case Question::TYPE_TAGS:
                    // XXX: universities without any tags are never shown!
                    $universities = $universities
                        ->left_outer_join('answer_tag', array('at' . $question->id . '.answer_id', '=', 'q' . $question->id . '.id'), 'at' . $question->id)
                        ->where_in('at' . $question->id . '.tag_id', $filterval);
                    break;
                case Question::TYPE_BOOLEAN:
                    $universities = $universities->where_in('q' . $question->id . '.value', $filterval);
                    break;
                case Question::TYPE_INTEGER:
                    if(is_array($filterval)) {
                        if(isset($filterval['min']) && $filterval['min'] != '-∞')
                            $universities = $universities->where_gte('q' . $question->id . '.value', intval($filterval['min']));
                        if(isset($filterval['max']) && $filterval['max'] != '∞' && $filterval['max'] != '+∞')
                            $universities = $universities->where_lte('q' . $question->id . '.value', intval($filterval['max']));
                    }
                    break;

            }
        }
    }
    $universities = $universities->select('university.*')->findMany();
    $questions = Question::findMany();
    $app->render('filter.html', array(
        'universities' => $universities,
        'questions' => $questions,
    ));
})->via('GET', 'POST')->name('filter');

$app->get('/university/:id', function($id) use ($app) {
    $university = University::findOne($id);
    if(!$university instanceof University) {
        $app->notFound();
    }
    $categories = Category::orderByAsc('order')->findMany();
    $app->render('university.html', array(
        'university' => $university,
        'categories' => $categories,
    ));
})->name('university');

$app->get('/university/:id/edit/:key', function($id, $key) use ($app) {
    $university = University::findOne($id);
    if(!$university instanceof University) {
        $app->notFound();
    }
    if($university->password != $key) {
        $app->render('denied.html', array(), 403);
        $app->stop();
    }
    $categories = Category::orderByAsc('order')->findMany();
    $app->render('university_edit.html', array(
        'university' => $university,
        'categories' => $categories,
    ));
})->name('university_edit');

$app->post('/university/:id/edit/:key', function($id, $key) use ($app) {
    $university = University::findOne($id);
    if(!$university instanceof University) {
        $app->notFound();
    }
    if($university->password != $key) {
        $app->render('denied.html', array(), 403);
        $app->stop();
    }
    $questions = Question::findMany();
    $answers = $university->answers();
    foreach($questions as $question) {
        $answer = $university->answers()->where('question_id', $question->id)->findOne();
        $newanswer = $app->request->post('q' . $question->id);
        if($newanswer != null) {
            if(!($answer instanceof Answer)) {
                $answer = Answer::create();
                $answer->university_id = $university->id;
                $answer->question_id = $question->id;
                $answer->save();
            }
            // question has been answered, create or update answer in database
            switch($question->type) {
                case Question::TYPE_INTEGER:
                    // XXX: check if value really is integer?
                    $answer->value = intval($newanswer);
                    break;
                case Question::TYPE_BOOLEAN:
                    $answer->value = ($newanswer == "1") ? "1" : "0";
                    break;
                case Question::TYPE_TAGS:
                    $answer->value = null;
                    if(is_array($newanswer)) {
                        // delete tags no longer present
                        $obsolete_tags = AnswerTag::where('answer_id', $answer->id)->where_not_in('tag_id', $newanswer)->findMany();
                        foreach($obsolete_tags as $answer_tag) {
                            $tag = Tag::findOne($answer_tag->tag_id);
                            $answer_tag->delete();
                            // delete tag itself if this was the last answer referring to it
                            if($tag->answers()->count() == 0) {
                                $tag->delete();
                            }
                        }
                        // add new tags
                        $tags = $answer->tags()->findMany();
                        $tag_ids = array();
                        foreach($tags as $tag) {
                            $tag_ids[] = $tag->id;
                        }
                        foreach($app->request->post('q' . $question->id) as $tag_id) {
                            if(!in_array($tag_id, $tag_ids)) {
                                $answer_tag = AnswerTag::create();
                                $answer_tag->tag_id = $tag_id;
                                $answer_tag->answer_id = $answer->id;
                                $answer_tag->save();
                            }
                        }
                    }
                    break;
            }
            $answer->freetext = $app->request->post('q' . $question->id . '_freetext');
            $answer->save();
        } elseif($answer instanceof Answer) {
            // question hasn't been answered, delete answer in database
            $answer->delete();
        }
    }
    $app->redirect($app->urlFor('university', array('id' => $id)));
});

$app->post('/question/:id/get_tag_id', function($id) use ($app) {
    /* XXX: This isn't authenticated in any way! Somebody who knows the URL can fill the database with unlimited tags... */
    $question = Question::findOne($id);
    $tagvalue = $app->request->post('tag');
    if(empty($tagvalue))
        return true;
    $tag = $question->tags()->where_like('value', $tagvalue)->findOne();
    if(!$tag instanceof Tag) {
        $tag = Tag::create();
        $tag->question_id = $question->id;
        $tag->value = $tagvalue;
        $tag->save();
    }
    echo $tag->id;
})->name('get_tag_id');

$app->run();
