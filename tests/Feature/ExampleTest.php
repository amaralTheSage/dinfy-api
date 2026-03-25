<?php

test('the application returns not found on the root route', function () {
    $response = $this->get('/');

    $response->assertStatus(404);
});
