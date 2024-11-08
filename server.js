import { Server } from 'socket.io';

const io = new Server(6001, {
    host: '0.0.0.0',
    cors: {
        origin: [
            "http://127.0.0.1:8000",
            "http://192.168.0.168:8000"
        ],
        methods: ["GET", "POST"],
        allowedHeaders: ["Content-Type"],
        credentials: true
    }
});

const streams = {};

io.on('connection', (socket) => {

    socket.on('broadcaster', () => {
        socket.broadcast.emit('broadcaster');
    });

    socket.on('viewer', ({ streamId }) => {
        if (streams[streamId]) {
            streams[streamId].viewers.push(socket.id);
            io.to(streams[streamId].broadcaster).emit('viewer-join', socket.id);
        } else {
            socket.emit('stream-not-found', { streamId });
        }
    });

    socket.on('offer', ({ offer, viewerId, streamId }) => {
        io.to(viewerId).emit('offer', { offer, viewerId, streamId });
    });

    socket.on('answer', ({ answer, viewerId, streamId }) => {
        if (streams[streamId]) {
            io.to(streams[streamId].broadcaster).emit('answer', { answer, viewerId, streamId });
        }
    });

    socket.on('ice-candidate', ({ candidate, viewerId, streamId }) => {
        io.to(viewerId).emit('ice-candidate', { candidate, streamId });
    });

    socket.on('viewer-ice-candidate', ({ candidate, streamId }) => {
        if (streams[streamId]) {
            io.to(streams[streamId].broadcaster).emit('viewer-ice-candidate', { candidate, viewerId: socket.id, streamId });
        }
    });

    socket.on('disconnect', () => {
        for (let streamId in streams) {
            const index = streams[streamId].viewers.indexOf(socket.id);
            if (index !== -1) {
                streams[streamId].viewers.splice(index, 1);
                io.to(streams[streamId].broadcaster).emit('viewer-disconnect', socket.id);
                console.log(`Viewer ${socket.id} disconnected from stream ${streamId}`);
                break;
            }
        }
    });

    socket.on('start-stream', ({ streamId }) => {
        if (streams[streamId]) {
            socket.emit('stream-exists', { streamId });
            return;
        }

        streams[streamId] = { broadcaster: socket.id, viewers: [] };

        const streamUrl = `http://127.0.0.1:8000/stream/${streamId}`;
        console.log(`Stream started! Stream URL: ${streamUrl}`);

        socket.emit('stream-url', { streamUrl });

        socket.broadcast.emit('broadcaster', { streamId });
    });

    socket.on('stop-stream', (streamId) => {
        if (streams[streamId] && streams[streamId].broadcaster === socket.id) {
            delete streams[streamId];
            io.emit('stream-stopped', { streamId });
            console.log(`Stream ${streamId} stopped by broadcaster ${socket.id}`);
        }
    });

    socket.on('viewer-join-stream', (streamId) => {
        if (streams[streamId]) {
            if (!streams[streamId].viewers.includes(socket.id)) {
                streams[streamId].viewers.push(socket.id);
                socket.emit('viewer-joined', { streamId });
                io.to(streams[streamId].broadcaster).emit('viewer-join', socket.id);
                console.log(`Viewer ${socket.id} joined stream ${streamId}`);
            } else {
                socket.emit('already-joined', { streamId });
            }
        } else {
            socket.emit('stream-not-found', { streamId });
        }
    });

    socket.on('viewer-disconnect-stream', (streamId) => {
        if (streams[streamId]) {
            const index = streams[streamId].viewers.indexOf(socket.id);
            if (index !== -1) {
                streams[streamId].viewers.splice(index, 1);
                io.to(streams[streamId].broadcaster).emit('viewer-disconnect', socket.id);
                console.log(`Viewer ${socket.id} left stream ${streamId}`);
            }
        }
    });
});