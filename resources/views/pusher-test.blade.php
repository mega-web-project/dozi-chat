<!DOCTYPE html>
<html>
<head>
  <title>Pusher Test</title>
</head>
<body>
  <h1>Pusher Test</h1>

  <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
  <script>
    Pusher.logToConsole = true;

  const pusher = new Pusher("{{ env('PUSHER_APP_KEY') }}", {
    cluster: "{{ env('PUSHER_APP_CLUSTER') }}"
  });

    const channel = pusher.subscribe('test-channel');

    channel.bind('test-event', function(data) {
      console.log('Received:', data);
      alert(data.message);
    });
  </script>
</body>
</html>
