<?php
/**
 * STDERR log writer. Writes out messages to STDERR.
 *
 * @package    Kohana
 * @category   Logging
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Log_StdErr extends Log_Writer {
	/**
	 * Writes each of the messages to STDERR.
	 *
	 *     $writer->write($messages);
	 *
	 * @param   array<int, array<string, mixed>>   $messages
	 * @return  void
	 */
	public function write(array $messages): void
	{
		foreach ($messages as $message)
		{
			// Writes out each message
			fwrite(STDERR, $this->format_message($message).PHP_EOL);
		}
	}

}
