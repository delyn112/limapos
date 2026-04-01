const { app, BrowserWindow, ipcMain, protocol, session, Menu, dialog } = require("electron");
const { autoUpdater } = require("electron-updater");

const { spawn } = require('child_process');
const path = require('path');
const url = require("url");
const { v4: uuidv4 } = require('uuid');
const fs = require('fs');
const logPath = path.join(app.getPath("userData"), "php-error.log");
let newWindow;
autoUpdater.autoDownload = true;
autoUpdater.autoInstallOnAppQuit = false;
let phpServer;


const createWindow = () => {
    newWindow = new BrowserWindow({
        width: 1200,
        height: 800,
        icon: path.join(__dirname, 'assets', 'icon.png'),
        webPreferences: {
            nodeIntegration: false,
            contextIsolation: true,
            preload: path.join(__dirname, 'preload.js') // for secure IPC
        }
    });




    const isDev = !app.isPackaged;


    if (isDev) {
        newWindow.loadURL("http://localhost:3000");
    } else {
        // Remove default menu
      Menu.setApplicationMenu(null);
        const indexPath = path.join(__dirname, '..', 'pos_app', 'build', 'index.html');
        newWindow.loadFile(indexPath);
    }
}


ipcMain.handle("get-printers", async (event) => {
     newWindow = BrowserWindow.getFocusedWindow();
    return await newWindow.webContents.getPrintersAsync();
});


//get the device Id
function getDeviceId() {
  const deviceIdPath = path.join(app.getPath('userData'), 'device-id.json');

  if (fs.existsSync(deviceIdPath)) {
    const data = JSON.parse(fs.readFileSync(deviceIdPath, 'utf-8'));
    return data.deviceId;
  } else {
    const newId = uuidv4();
    fs.writeFileSync(deviceIdPath, JSON.stringify({ deviceId: newId }));
    return newId;
  }
}

ipcMain.handle('get-device-id', () => {
  return getDeviceId();
});


ipcMain.on("print-order", function (event, data) {
    printOrder(data)
});


function startPHPServer() {
    const phpPath = app.isPackaged
        ? path.join(process.resourcesPath, "app.asar.unpacked/php/php.exe")
        : path.join(__dirname, "../php/php.exe");

    const wwwPath = app.isPackaged
        ? path.join(process.resourcesPath, "app.asar.unpacked/www")
        : path.join(__dirname, "../www");

    phpServer = spawn(phpPath, ["-S", "127.0.0.1:8004", "-t", wwwPath]);

    phpServer.stdout.on("data", (data) => {
        const msg = `PHP error: ${data}\n`;
    fs.appendFileSync(logPath, msg);
    });

    phpServer.stderr.on("data", (data) => {
       const msg = `PHP error: ${data}\n`;
    fs.appendFileSync(logPath, msg);
    });

    phpServer.on("close", (code) => {
         const msg = `PHP server exited with code ${code}\n`;
    fs.appendFileSync(logPath, msg);
    });
}



function printOrder(data) {

    // Wait 500ms before fetching the PHP page
    setTimeout(() => {
        fetch('http://127.0.0.1:8004', {  // your PHP script
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
            .then(res => res.text())
            .then(output => console.log('PHP output:', output))
            .catch(err => console.error('Fetch error:', err));
    }, 500);
}



// Auto-Updater Setup
const setupAutoUpdater = () => {
    autoUpdater.checkForUpdates();

    autoUpdater.on("update-available", () => {
        dialog.showMessageBox({
            type: "info",
            title: "Update Available",
            message: "A new version has been released and is downloading..."
        });
    });


    autoUpdater.on("update-downloaded", () => {
        dialog.showMessageBox({
            type: "info",
            title: "Update Ready",
            message: "Update downloaded. Restart now?",
            buttons: ["Yes", "Later"]
        }).then(async (result) => {
            if (result.response === 0) {
                try {
                    await session.defaultSession.clearStorageData({
                        storages: [
                            'appcache',
                            'cookies',
                            'filesystem',
                            'indexdb',
                            'localstorage',
                            'shadercache',
                            'websql',
                            'serviceworkers'
                        ]
                    });
                } catch (err) {
                    console.error("Failed to clear session:", err);
                    dialog.showErrorBox(
                        "Update Error",
                        "Failed to clear session:\n" + err.message
                    );
                }
                autoUpdater.quitAndInstall();
            }
        });
    });
};

//start the app when system is ready
app.whenReady().then(() => {
    createWindow();
    startPHPServer();
    if (!app.isPackaged) return; // skip auto-updates in dev
    setupAutoUpdater();

    app.on("activate", () => {
        if (BrowserWindow.getAllWindows().length === 0) createWindow();
    });
})

app.on('window-all-closed', () => {
    if (process.platform !== 'darwin') app.quit();
})

