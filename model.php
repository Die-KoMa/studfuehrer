<?php
class University extends Model {
    public function answers() {
        return $this->has_many('Answer');
    }
}
class Question extends Model {
    const TYPE_TAGS = 'tc';
    const TYPE_INTEGER = 'i';
    const TYPE_FREETEXT = 'f';
    const TYPE_BOOLEAN = 'b';
    public function category() {
        return $this->belongs_to('Category');
    }
    public function answers() {
        return $this->has_many('Answer');
    }
    public function tags() {
        return $this->has_many('Tag');
    }
}
class Category extends Model {
    public function questions() {
        return $this->has_many('Question');
    }
}
class Answer extends Model {
    public function university() {
        return $this->belongs_to('University');
    }
    public function tags() {
        return $this->has_many_through('Tag');
    }
    public function question() {
        return $this->belongs_to('Question');
    }
}
class Tag extends Model {
    public function answers() {
        return $this->has_many_through('Answer');
    }
    public function question() {
        return $this->belongs_to('Question');
    }
}
class AnswerTag extends Model {}
