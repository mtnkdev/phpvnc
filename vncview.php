<?php
session_start();

$host = $_SESSION['host'];
$port = $_SESSION['port'];
$passwd = $_SESSION['passwd'];
$socket = $_SESSION['socket'];
$sesid = $_COOKIE['PHPSESSID'];

?>
<html>
<script src="//ajax.googleapis.com/ajax/libs/jquery/2.1.0/jquery.min.js"></script>

<button type="button" onclick="redraw();">Redraw</button>
<div id="vnccontainer">
  <img id="vncviewer" src="vncimg.php" />
</div>

<script type="text/javascript">

  // "asdf".getBytes();
  String.prototype.getBytes = function () {
	var bytes = [];
	for (var i = 0; i < this.length; ++i) {
	  bytes.push(this.charCodeAt(i));
	}
	return bytes;
  };
  
  function hexToArray(str) {
	var a = [];
	for (var i = 0; i < str.length; i += 2) {
	  a.push("0x" + str.substr(i, 2));
	}
	return(a);
  }
  
  /* Convert value as 8-bit unsigned integer to 2 digit hexadecimal number. */
  function hex8(val) {
    val &= 0xFF;
    var hex = val.toString(16).toUpperCase();
    return ("00" + hex).slice(-2);
  }

  /* Convert value as 16-bit unsigned integer to 4 digit hexadecimal number. */
  function hex16(val) {
	  val &= 0xFFFF;
	  var hex = val.toString(16).toUpperCase();
	  return ("0000" + hex).slice(-4);
  }
  
  /* Convert value as 32-bit unsigned integer to 8 digit hexadecimal number. */
  function hex32(val) {
	  val &= 0xFFFFFFFF;
	  var hex = val.toString(16).toUpperCase();
	  return ("00000000" + hex).slice(-8);
  }
  
  function binaryToHex(s) {
	var i, k, part, accum, ret = '';
	for (i = s.length-1; i >= 3; i -= 4) {
		// extract out in substrings of 4 and convert to hex
		part = s.substr(i+1-4, 4);
		accum = 0;
		for (k = 0; k < 4; k += 1) {
			if (part[k] !== '0' && part[k] !== '1') {
				// invalid character
				return { valid: false };
			}
			// compute the length 4 substring
			accum = accum * 2 + parseInt(part[k], 10);
		}
		if (accum >= 10) {
			// 'A' to 'F'
			ret = String.fromCharCode(accum - 10 + 'A'.charCodeAt(0)) + ret;
		} else {
			// '0' to '9'
			ret = String(accum) + ret;
		}
	}
	// remaining characters, i = 0, 1, or 2
	if (i >= 0) {
		accum = 0;
		// convert from front
		for (k = 0; k <= i; k += 1) {
			if (s[k] !== '0' && s[k] !== '1') {
				return { valid: false };
			}
			accum = accum * 2 + parseInt(s[k], 10);
		}
		// 3 bits, value cannot exceed 2^3 - 1 = 7, just convert
		ret = String(accum) + ret;
	}
	return { valid: true, result: ret };
  }

  var mouseMoved = false;
  var mouseX = 0;
  var mouseY = 0;
  var mouseLeft = 0;
  var mouseRight = 0;
  var mouseMiddle = 0;
  var mouseScrollUp = 0;
  var mouseScrollDown = 0;
  
  function sendMouseEvent(x, y, lbutton, rbutton, mbutton, sup, sdown) {
	var bytes = [];
	var buttonMask;
	var X;
	var Y;
	buttonMask = parseInt("0x" + binaryToHex( lbutton.toString() + rbutton.toString() + mbutton.toString() + sup.toString() + sdown.toString() + '000').result);
	console.log(buttonMask);
	//bytes = [0x05, buttonMask, 0x00, X, 0x00, Y];
	bytes.push(0x05);
	bytes.push(buttonMask);
	X = hexToArray(hex16(x));
	console.log("X: " + x);
	bytes.push(parseInt(X[0]));
	bytes.push(parseInt(X[1]));
	Y = hexToArray(hex16(y));
	console.log("Y: " + y);
	bytes.push(parseInt(Y[0]));
	bytes.push(parseInt(Y[1]));
	console.log(bytes);
	
	$.post("vncevent.php", JSON.stringify({ shid: '<?=$_SESSION['shid']?>', op: 'rawmsg', rawdata: bytes }));
  }

  setInterval(function(){
	// send mouse coordinates to RFB every 1 sec if the mouse got moved
	if (mouseMoved == true) {
	  sendMouseEvent(mouseX, mouseY, mouseLeft, mouseRight, mouseMiddle, mouseScrollUp, mouseScrollDown);
	  mouseMoved = false;
	}
  }, 1000);
  
  $('#vncviewer').mousemove(function(e) {
	var offset = $(this).offset();
	mouseMoved = true;
	//console.log(e.clientX - offset.left);
	//console.log(e.clientY - offset.top);
	mouseX = (e.clientX - offset.left);
	mouseY = (e.clientY - offset.top);
  });

	function keyPress(e, upDown) {
	  var keyCode = e.keyCode;
	  var spKey = 0;
	  var retcode = true;
	  
	  console.log('keyPress(' + upDown + '); keycode ' + keyCode);
	  
	  // specials
	  if (keyCode == 8) { // backspace
		spKey = 0xff;
		retcode = false;
	  }
	  
	  if (keyCode == 13) { // enter
		spKey = 0xff;
		retcode = false;
	  }
	  	  
	  if (keyCode == 16) {
		// left shift
		spKey = 0xff;
		keyCode = 0xe1;
		retcode = false;
	  }
	  
	  if (keyCode == 17) {
		//control
		spKey = 0xff;
		keyCode = 0xe3;
		retcode = false;
	  }
	  
	  var bytes;
	  bytes = [0x04, upDown, 0x00, 0x00, 0x00, 0x00, spKey, keyCode];
	  $.post("vncevent.php", JSON.stringify({ shid: '<?=$_SESSION['shid']?>', op: 'rawmsg', rawdata: bytes }));
	  return(retcode);
	}
	
	$(document).keydown(function(e){
	  return(keyPress(e, 1));
	});

	$(document).keyup(function(e){
	  return(keyPress(e, 0));
	});
	
	
	function redraw() {
	  var bytes = [0x03, 0x00, 0x00, 0x00, 0x00, 0x00];
	  $.post("vncevent.php", JSON.stringify({ shid: '<?=$_SESSION['shid']?>', op: 'rawmsg', rawdata: bytes }));
	}
	
</script>

</html>
