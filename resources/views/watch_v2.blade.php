<!DOCTYPE html>
<html>

<head>
    <title>Watch Stream</title>

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

        .content {
            text-align: center;
        }

        h1 {
            color: #333;
            margin-bottom: 20px;
        }

        #remote-video {
            width: 80%;
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
            margin-top: 10px;
        }

        button:hover {
            background-color: #0056b3;
        }

        #leave-button {
            background-color: #dc3545;
            display: none;
        }
    </style>
</head>

<body>
    <div class="content">
        <h1>Watch Stream</h1>
        <video id="remote-video" autoplay playsinline controls></video>
        <div>
            <button id="play-button">Play Stream</button>
            <button id="leave-button" style="display: none;">Leave Stream</button>
            <button id="record-button" style="display: none;">Record Stream</button>
            <button id="stop-recording-button" style="display: none;">Stop Recording</button>
            <button id="download-recording" style="display: none;">Download Recording</button>
        </div>
    </div>

    <script src="https://cdn.socket.io/4.4.1/socket.io.min.js"></script>
    <script>
        const socket = io('{{ env("APP_SOCKET_URL", "http://127.0.0.1:6001") }}');
        const remoteVideo = document.getElementById('remote-video');
        const playButton = document.getElementById('play-button');
        const leaveButton = document.getElementById('leave-button');
        const recordButton = document.getElementById('record-button');
        const stopRecordingButton = document.getElementById('stop-recording-button');
        const downloadButton = document.getElementById('download-recording');
        const streamId = window.location.pathname.split('/').pop();

        let peerConnection = null;
        let mediaRecorder = null;
        let recordedChunks = [];
        let downloadUrl = null;

        socket.emit('viewer', {
            streamId
        });

        socket.on('offer', async ({
            offer,
            viewerId,
            streamId: offerStreamId
        }) => {
            if (offerStreamId === streamId) {
                peerConnection = new RTCPeerConnection({
                    iceServers: [{
                        urls: 'stun:stun.l.google.com:19302'
                    }]
                });

                peerConnection.ontrack = (event) => {
                    const stream = event.streams[0];
                    remoteVideo.srcObject = stream;
                    recordButton.style.display = 'inline-block';
                };

                await peerConnection.setRemoteDescription(new RTCSessionDescription(offer));
                const answer = await peerConnection.createAnswer();
                await peerConnection.setLocalDescription(answer);

                socket.emit('answer', {
                    answer,
                    viewerId,
                    streamId
                });
            }
        });

        socket.on('ice-candidate', ({
            candidate,
            streamId: candidateStreamId
        }) => {
            if (candidateStreamId === streamId) {
                peerConnection.addIceCandidate(new RTCIceCandidate(candidate));
            }
        });

        playButton.addEventListener('click', () => {
            if (!peerConnection) {
                startStream();
            }

            remoteVideo.play().then(() => {
                playButton.style.display = 'none';
                leaveButton.style.display = 'inline-block';
            }).catch((err) => console.error('Error playing video:', err));
        });

        function startStream() {
            console.log("Starting stream connection...");
            peerConnection = new RTCPeerConnection({
                iceServers: [{
                    urls: 'stun:stun.l.google.com:19302'
                }]
            });

            socket.emit('viewer', {
                streamId
            });

            socket.on('offer', async ({
                offer,
                viewerId,
                streamId: offerStreamId
            }) => {
                if (offerStreamId === streamId) {
                    await peerConnection.setRemoteDescription(new RTCSessionDescription(offer));
                    const answer = await peerConnection.createAnswer();
                    await peerConnection.setLocalDescription(answer);

                    socket.emit('answer', {
                        answer,
                        viewerId,
                        streamId
                    });
                    console.log("Answer sent for new offer.");

                    peerConnection.ontrack = (event) => {
                        const stream = event.streams[0];
                        remoteVideo.srcObject = stream;
                        recordButton.style.display = 'inline-block';
                        console.log("Received and set up remote stream.");
                    };
                }
            });

            socket.on('ice-candidate', ({
                candidate,
                streamId: candidateStreamId
            }) => {
                if (candidateStreamId === streamId) {
                    peerConnection.addIceCandidate(new RTCIceCandidate(candidate));
                    console.log("Added ICE candidate.");
                }
            });
        }

        recordButton.addEventListener('click', () => {
            if (remoteVideo.srcObject) {
                console.log("Starting recording...");
                startRecording(remoteVideo.srcObject);
                recordButton.style.display = 'none';
                stopRecordingButton.style.display = 'inline-block';
            } else {
                console.log("No stream available to record.");
            }
        });

        function startRecording(stream) {
            recordedChunks = [];
            mediaRecorder = new MediaRecorder(stream, {
                mimeType: 'video/webm; codecs=vp9'
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

                const date = new Date();
                const formattedDate =
                    `${date.getFullYear()}${('0' + (date.getMonth() + 1)).slice(-2)}${('0' + date.getDate()).slice(-2)}`;

                downloadButton.download = `Live-${formattedDate}.webm`;
                downloadButton.style.display = 'inline-block';
            };

            mediaRecorder.start();
            console.log("Recording started.");
        }

        stopRecordingButton.addEventListener('click', () => {
            if (mediaRecorder && mediaRecorder.state !== 'inactive') {
                mediaRecorder.stop();
                stopRecordingButton.style.display = 'none';
            } else {
                console.log("Recording is not active.");
            }
        });

        downloadButton.addEventListener('click', () => {
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
        });

        leaveButton.addEventListener('click', () => {
            if (peerConnection) {
                peerConnection.close();
                peerConnection = null;
                remoteVideo.srcObject = null;

                leaveButton.style.display = 'none';
                playButton.style.display = 'inline-block';
                recordButton.style.display = 'none';
                stopRecordingButton.style.display = 'none';
                downloadButton.style.display = 'none';

                socket.emit('leave-stream', {
                    streamId
                });
                console.log("Left the stream and reset UI.");
            }
        });
    </script>
</body>

</html>
