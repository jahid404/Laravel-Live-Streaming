<!DOCTYPE html>
<html>

<head>
    <title>Broadcast Stream with Screen Sharing</title>
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

        .buttons {
            display: flex;
            justify-content: center;
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
            margin: 10px 5px;
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
    <h1>Broadcast Your Stream</h1>
    <video id="local-video" autoplay playsinline></video>

    <div class="buttons">
        <button id="start-stream">Start Streaming</button>
        <button id="stop-stream" style="display: none;">Stop Streaming</button>
        <button id="share-screen" style="display: none;">Share Screen</button>
        <button id="stop-screen-share" style="display: none;">Stop Screen Share</button>
        <button id="toggle-video" style="display: none;">Turn Video Off</button>
        <button id="toggle-audio" style="display: none;">Mute Audio</button>

        <button id="record-button" style="display: none;">Record Stream</button>
        <button id="stop-recording-button" style="display: none;">Stop Recording</button>
        <button id="download-recording" style="display: none;">Download Recording</button>
    </div>
    <a id="stream-url" href="" target="_blank">View Stream</a>

    <script src="https://cdn.socket.io/4.4.1/socket.io.min.js"></script>
    <script>
        const socket = io('http://127.0.0.1:6001');
        const localVideo = document.getElementById('local-video');
        const startStreamButton = document.getElementById('start-stream');
        const stopStreamButton = document.getElementById('stop-stream');
        const shareScreenButton = document.getElementById('share-screen');
        const stopScreenShareButton = document.getElementById('stop-screen-share');
        const toggleVideoButton = document.getElementById('toggle-video');
        const toggleAudioButton = document.getElementById('toggle-audio');
        const streamUrlButton = document.getElementById('stream-url');

        const recordButton = document.getElementById('record-button');
        const stopRecordingButton = document.getElementById('stop-recording-button');
        const downloadButton = document.getElementById('download-recording');

        let streamId = null;
        let peerConnections = {};
        let mediaStream = null;
        let isVideoEnabled = true;
        let isAudioEnabled = true;

        let mediaRecorder = null;
        let recordedChunks = [];
        let downloadUrl = null;

        function resetPeerConnections() {
            for (const viewerId in peerConnections) {
                if (peerConnections.hasOwnProperty(viewerId)) {
                    peerConnections[viewerId].close();
                    delete peerConnections[viewerId];
                }
            }
        }

        function generateStreamId() {
            const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            let streamId = '';
            for (let i = 0; i < 9; i++) {
                if (i === 3 || i === 6) streamId += '-';
                streamId += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            return streamId;
        }

        async function startBroadcast() {
            mediaStream = await navigator.mediaDevices.getUserMedia({
                video: true,
                audio: true
            });
            localVideo.srcObject = mediaStream;

            streamId = generateStreamId();
            socket.emit('start-stream', {
                streamId
            });

            const streamUrl =
                `${window.location.protocol}//${window.location.hostname}:${window.location.port}/stream/${streamId}`;
            streamUrlButton.href = streamUrl;
            streamUrlButton.style.display = 'inline-block';

            startStreamButton.style.display = 'none';
            stopStreamButton.style.display = 'inline';
            shareScreenButton.style.display = 'inline';
            toggleVideoButton.style.display = 'inline';
            toggleAudioButton.style.display = 'inline';
            recordButton.style.display = 'inline';

            socket.on('viewer-join', async (viewerId) => {
                const peerConnection = new RTCPeerConnection({
                    iceServers: [{
                        urls: 'stun:stun.l.google.com:19302'
                    }]
                });
                peerConnections[viewerId] = peerConnection;

                mediaStream.getTracks().forEach(track => peerConnection.addTrack(track, mediaStream));

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

        async function shareScreen() {
            try {
                const screenStream = await navigator.mediaDevices.getDisplayMedia({
                    video: true
                });
                localVideo.srcObject = screenStream;

                screenStream.getTracks().forEach(track => {
                    track.onended = () => stopScreenShare();
                    updateTrack(track);
                });

                shareScreenButton.style.display = 'none';
                stopScreenShareButton.style.display = 'inline';
            } catch (err) {
                console.error("Error sharing screen:", err);
            }
        }

        function stopScreenShare() {
            localVideo.srcObject = mediaStream;
            stopScreenShareButton.style.display = 'none';
            shareScreenButton.style.display = 'inline';
            mediaStream.getTracks().forEach(track => updateTrack(track));
        }

        function updateTrack(track) {
            for (const viewerId in peerConnections) {
                const sender = peerConnections[viewerId].getSenders().find(s => s.track.kind === track.kind);
                if (sender) sender.replaceTrack(track);
            }
        }

        function toggleVideo() {
            isVideoEnabled = !isVideoEnabled;
            mediaStream.getVideoTracks().forEach(track => track.enabled = isVideoEnabled);
            toggleVideoButton.textContent = isVideoEnabled ? "Turn Video Off" : "Turn Video On";
        }

        function toggleAudio() {
            isAudioEnabled = !isAudioEnabled;
            mediaStream.getAudioTracks().forEach(track => track.enabled = isAudioEnabled);
            toggleAudioButton.textContent = isAudioEnabled ? "Mute Audio" : "Unmute Audio";
        }

        function stopBroadcast() {
            if (mediaStream) {
                mediaStream.getTracks().forEach(track => track.stop());
                localVideo.srcObject = null;
            }
            socket.emit('stop-stream', {
                streamId
            });
            startStreamButton.style.display = 'inline';
            stopStreamButton.style.display = 'none';
            shareScreenButton.style.display = 'none';
            stopScreenShareButton.style.display = 'none';
            toggleVideoButton.style.display = 'none';
            toggleAudioButton.style.display = 'none';
            streamUrlButton.style.display = 'none';
            resetPeerConnections();
        }

        function startRecording() {
            if (mediaRecorder && mediaRecorder.state !== "inactive") {
                console.log("Already recording.");
                return;
            }
            recordedChunks = [];
            mediaRecorder = new MediaRecorder(mediaStream, {
                mimeType: 'video/webm'
            });
            mediaRecorder.ondataavailable = (event) => {
                if (event.data.size > 0) recordedChunks.push(event.data);
            };
            mediaRecorder.onstop = () => {
                const blob = new Blob(recordedChunks, {
                    type: 'video/webm'
                });
                downloadUrl = URL.createObjectURL(blob);
                downloadButton.href = downloadUrl;
                downloadButton.download = `recorded_stream_${new Date().toISOString()}.webm`;
                downloadButton.style.display = 'inline';
            };
            mediaRecorder.start();
            recordButton.style.display = 'none';
            stopRecordingButton.style.display = 'inline';
        }

        function stopRecording() {
            if (mediaRecorder && mediaRecorder.state === "recording") {
                mediaRecorder.stop();
                stopRecordingButton.style.display = 'none';
                recordButton.style.display = 'inline';
                if (downloadUrl) {
                    URL.revokeObjectURL(downloadUrl);
                    downloadUrl = null;
                }
            }
        }

        function downloadRecordedStream() {
            if (downloadUrl) {
                const date = new Date();
                const formattedDate =
                    `${date.getFullYear()}${('0' + (date.getMonth() + 1)).slice(-2)}${('0' + date.getDate()).slice(-2)}`;
                const link = document.createElement('a');
                link.href = downloadUrl;
                link.download = `Live-${formattedDate}.webm`;
                link.click();
            } else {
                console.log("No recording available for download.");
            }
        }

        startStreamButton.addEventListener('click', startBroadcast);
        stopStreamButton.addEventListener('click', stopBroadcast);
        shareScreenButton.addEventListener('click', shareScreen);
        stopScreenShareButton.addEventListener('click', stopScreenShare);
        toggleVideoButton.addEventListener('click', toggleVideo);
        toggleAudioButton.addEventListener('click', toggleAudio);
        recordButton.addEventListener('click', startRecording);
        stopRecordingButton.addEventListener('click', stopRecording);
        downloadButton.addEventListener('click', downloadRecordedStream);
    </script>
</body>

</html>
