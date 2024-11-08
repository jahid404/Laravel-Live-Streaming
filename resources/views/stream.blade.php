<!DOCTYPE html>
<html>

<head>
    <title>Broadcast Stream</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f9;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        h1 {
            color: #333;
            margin-bottom: 20px;
        }

        #local-video {
            width: 60%;
            max-width: 500px;
            border: 2px solid #ddd;
            margin: 20px auto;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        button {
            background-color: #007bff;
            color: white;
            padding: 10px 20px;
            font-size: 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
            margin: 10px 0;
        }

        button:hover {
            background-color: #0056b3;
        }

        button:active {
            background-color: #003f7f;
        }

        button:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
        }

        #stream-url {
            display: none;
            background-color: #28a745;
            color: white;
            padding: 10px 20px;
            font-size: 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 20px;
            text-decoration: none;
            transition: background-color 0.3s;
        }

        #stream-url:hover {
            background-color: #218838;
        }

        #stream-url:active {
            background-color: #1e7e34;
        }
    </style>
</head>

<body>
    <h1>Broadcast Stream</h1>
    <video id="local-video" autoplay muted playsinline></video>

    <button id="start-stream">Start Streaming</button>
    <button id="stop-stream" style="display: none;">Stop Streaming</button>
    <a id="stream-url" href="" target="_blank">View Stream</a>

    <script src="https://cdn.socket.io/4.4.1/socket.io.min.js"></script>
    <script>
        const socket = io('http://127.0.0.1:6001');
        const localVideo = document.getElementById('local-video');
        const startStreamButton = document.getElementById('start-stream');
        const stopStreamButton = document.getElementById('stop-stream');
        const streamUrlButton = document.getElementById('stream-url');

        let streamId = null;
        let peerConnections = {};

        function resetPeerConnections() {
            for (const viewerId in peerConnections) {
                if (peerConnections.hasOwnProperty(viewerId)) {
                    peerConnections[viewerId].close();
                    delete peerConnections[viewerId];
                }
            }
        }

        async function startBroadcast() {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: true,
                audio: true
            });
            localVideo.srcObject = stream;

            streamId = 'stream-' + Date.now();
            socket.emit('start-stream', {
                streamId
            });

            /* const streamUrl =
                `${window.location.protocol}//${window.location.hostname}:${window.location.port}/stream/${streamId}`; */
            const streamUrl =
                `http://127.0.0.1:8000/stream/${streamId}`;
            streamUrlButton.href = streamUrl;
            streamUrlButton.style.display = 'inline-block';

            const urlElement = document.createElement('p');
            urlElement.textContent = `Share this URL with viewers: ${streamUrl}`;
            document.body.appendChild(urlElement);

            startStreamButton.style.display = 'none';
            stopStreamButton.style.display = 'inline';

            socket.on('viewer-join', async (viewerId) => {
                const peerConnection = new RTCPeerConnection({
                    iceServers: [{
                        urls: 'stun:stun.l.google.com:19302'
                    }]
                });
                peerConnections[viewerId] = peerConnection;

                stream.getTracks().forEach(track => peerConnection.addTrack(track, stream));

                const offer = await peerConnection.createOffer();
                await peerConnection.setLocalDescription(offer);
                socket.emit('offer', {
                    offer,
                    viewerId,
                    streamId
                });

                peerConnection.onicecandidate = (event) => {
                    if (event.candidate) {
                        socket.emit('ice-candidate', {
                            candidate: event.candidate,
                            viewerId,
                            streamId
                        });
                    }
                };
            });

            socket.on('answer', ({
                answer,
                viewerId
            }) => {
                peerConnections[viewerId].setRemoteDescription(new RTCSessionDescription(answer));
            });

            socket.on('viewer-ice-candidate', ({
                candidate,
                viewerId
            }) => {
                peerConnections[viewerId].addIceCandidate(new RTCIceCandidate(candidate));
            });

            socket.on('viewer-disconnect', (viewerId) => {
                if (peerConnections[viewerId]) {
                    peerConnections[viewerId].close();
                    delete peerConnections[viewerId];
                }
            });
        }

        function stopBroadcast() {
            if (localVideo.srcObject) {
                let tracks = localVideo.srcObject.getTracks();
                tracks.forEach(track => track.stop());
                localVideo.srcObject = null;
            }
            socket.emit('stop-stream', {
                streamId
            });
            startStreamButton.style.display = 'inline';
            stopStreamButton.style.display = 'none';
            streamUrlButton.style.display = 'none';

            resetPeerConnections();
        }

        startStreamButton.addEventListener('click', startBroadcast);
        stopStreamButton.addEventListener('click', stopBroadcast);
    </script>
</body>

</html>
