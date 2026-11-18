<?php

test('the application returns a successful response', function () {
    $this->get('/')
        ->assertOk()
        ->assertExactJson([
            'name' => 'Titan',
            'health' => 'ok',
        ]);
});
