<?php

it('sends guests from the home page to the login screen', function () {
    $this->get('/')->assertRedirect(route('login'));
});
