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

        #play-button,
        #leave-button {
            background-color: #007bff;
            color: white;
            padding: 10px 20px;
            font-size: 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 10px;
        }

        #play-button:hover,
        #leave-button:hover {
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
            <button id="leave-button">Leave Stream</button>
        </div>
    </div>

    <script src="https://cdn.socket.io/4.4.1/socket.io.min.js"></script>
    <script>
        const socket = io('http://127.0.0.1:6001');
        const remoteVideo = document.getElementById('remote-video');
        const playButton = document.getElementById('play-button');
        const leaveButton = document.getElementById('leave-button');
        const streamId = window.location.pathname.split('/').pop();
        let peerConnection = null;

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
                    remoteVideo.srcObject = event.streams[0];
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

                    peerConnection.ontrack = (event) => {
                        remoteVideo.srcObject = event.streams[0];
                    };
                }
            });

            /* socket.on('ice-candidate', ({
                candidate,
                streamId: candidateStreamId
            }) => {
                if (candidateStreamId === streamId) {
                    peerConnection.addIceCandidate(new RTCIceCandidate(candidate));
                }
            }); */
            socket.on('ice-candidate', ({
                candidate,
                streamId: candidateStreamId
            }) => {
                if (candidateStreamId === streamId) {
                    peerConnection.addIceCandidate(new RTCIceCandidate(candidate));
                }
            });
        }

        leaveButton.addEventListener('click', () => {
            if (peerConnection) {
                peerConnection.close();
                peerConnection = null;
                remoteVideo.srcObject = null;

                leaveButton.style.display = 'none';
                playButton.style.display = 'inline-block';

                socket.emit('leave-stream', {
                    streamId
                });
            }
        });
    </script>
</body>

</html>
