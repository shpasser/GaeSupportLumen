<?php namespace Shpasser\GaeSupportLumen\Mail;

use Illuminate\Mail\TransportManager;
use Shpasser\GaeSupportLumen\Mail\Transport\GaeTransport;

class GaeTransportManager extends TransportManager {

	protected function createGaeDriver()
	{
		return new GaeTransport($this->app);
	}

}