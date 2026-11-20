<?php
/**
 * The first page of a new application: replace it with your own.
 */
class Controller_Welcome extends Controller {

	public function action_index(): void
	{
		$this->response->body('hello, world!');
	}

}
