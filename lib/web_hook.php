<?php defined("GITHOOK_VERSION") || exit;

class WebHook {
	private $token;
	private $repository;
	private $repo_path;
	private $branch;
	private $algo;

	public function __construct(string $token, string $repository,
		string $repo_path, string $branch) {

		$this->token = $token;
		$this->repository = $repository;
		$this->repo_path = $repo_path;
		$this->branch = $branch;
		$this->git = "git";

		$this->execute();
	}

	public function execute(): void {
		$content = file_get_contents("php://input");
		if (! $content) {
			$this->response(400, "No content received.");
			return;
		}

		$json = json_decode($content, true);
		if (! $json) {
			$this->response(400, "The content received was not JSON. Please ensure you've set your webhook content type as 'application/json' on GitHub.");
			return;
		}

		if (! $token && isset($_SERVER["HTTP_X_HUB_SIGNATURE"])) {
			list($algo, $token) = explode("=", $_SERVER["HTTP_X_HUB_SIGNATURE"], 2) + array("", "");
		} elseif (isset($_SERVER["HTTP_X_GITLAB_$this->token"])) {
			$token = $_SERVER["HTTP_X_GITLAB_$this->token"];
		} elseif (isset($_GET["token"])) {
			$token = $_GET["token"];
		}

		if (isset($json["checkout_sha"])) {
			$sha = $json["checkout_sha"];
		} elseif (isset($_SERVER["checkout_sha"])) {
			$sha = $_SERVER["checkout_sha"];
		} elseif (isset($_GET["sha"])) {
			$sha = $_GET["sha"];
		}

		$final_buffer = "";

		// Check for a GitHub signature
		if (!empty($this->token) && isset($_SERVER["HTTP_X_HUB_SIGNATURE"]) && $token !== hash_hmac($algo, $content, $this->token)) {
			$this->response(403, "X-Hub-Signare does not match secret key");
			return;
		// Check for a GitLab token
		} elseif (!empty($this->token) && isset($_SERVER["HTTP_X_GITLAB_$this->token"]) && $token !== $this->token) {
			$this->response(403, "X-GitLab-Token does not match secret key");
			return;
		// Check for a $_GET token
		} elseif (!empty($this->token) && isset($_GET["token"]) && $token !== $this->token) {
			$this->response(403, "Query param 'token' does not match secret key");
			return;
		// If none of the above match, but a token exists, exit
		} elseif (!empty($this->token) && !isset($_SERVER["HTTP_X_HUB_SIGNATURE"]) && !isset($_SERVER["HTTP_X_GITLAB_$this->token"]) && !isset($_GET["token"])) {
			$this->response(403, "No token was provided to verify against the secret key");
			return;
		} else {
			// Only execute on matching branch events.
			if ($json["ref"] === $this->branch) {

				// Make sure the directory is a repository.
				if (file_exists($this->repo_path . "/.git") && is_dir($this->repo_path)) {
					chdir($this->repo_path);

					/**
					* Attempt to reset specific hash if specified
					*/
					if (! empty($_GET["reset"]) && $_GET["reset"] === "true") {
						exec($this->git . " reset --hard HEAD 2>&1", $output, $exit);

						// Reformat the output as string.
						$output = (! empty($output) ? implode("\n", $output) : "") . "\n";

						if ($exit !== 0) {
							$this->response(500, sprintf("=== ERROR: Reset to head failed in '%s' ===\n%s",
								$this->repo_path, $output));
							return;
						}

						$final_buffer .= sprintf("=== Reset to head OK ===\n%s", $output);
					}


					/**
					 * NOTE: This is where pre git event hooks could be executed.
					 */

					/**
					* Attempt to pull, returing the output and exit code
					*/
					exec($this->git . " pull 2>&1", $output, $exit);
					$output = (! empty($output) ? implode("\n", $output) : "") . "\n";

					if ($exit !== 0) {
						$this->response(500, sprintf("=== ERROR: Pull failed in '%s' ===\n%s",
							$this->repo_path, $output));
						return;
					}

					$final_buffer .= sprintf("\n=== Pull OK ===\n%s", $output);

					/**
					* Attempt to checkout specific hash if specified
					*/
					if (! empty($sha)) {
						exec($this->git . " reset --hard {$sha} 2>&1", $output, $exit);
						$output = (! empty($output) ? implode("\n", $output) : "") . "\n";

						// if an error occurred, return 500 and log the error
						if ($exit !== 0) {
							$this->response(500, sprintf("=== ERROR: Reset to hash using SHA '%s' in '%s' failed ===\n%s",
								$sha, $this->repo_path, $output));
							return;
						}

						$final_buffer .= sprintf("\n=== Reset to hash '%s' OK ===\n%s", $sha, $output);
					}

					/**
					 * NOTE: This is where post git event hooks could be executed.
					 */

					$this->response(200, $final_buffer);
					return;

				} else {
					// prepare the generic error
					$error = "=== ERROR: DIR `" . $this->repo_path . "` is not a repository ===\n";

					// try to detemrine the real error
					if (!file_exists($this->repo_path)) {
						$error = "=== ERROR: DIR `" . $this->repo_path . "` does not exist ===\n";
					} elseif (!is_dir($this->repo_path)) {
						$error = "=== ERROR: DIR `" . $this->repo_path . "` is not a directory ===\n";
					}

					$this->response(400, $error);
				}
			} else {
				$this->response(200, "Event was a different branch.");
				return;
			}
		}

	}

	public function response(int $http_status_code = 500,
		string $message = "An error occurred"): void {

		http_response_code($http_status_code);
		header("Content-Type: text/plain");
		echo $message;
		exit;
	}
}
