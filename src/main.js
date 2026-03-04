const { app, BrowserWindow, ipcMain, protocol, session, autoUpdater, Menu } = require("electron");
const { spawn } = require('child_process');
const path = require('path');
const url = require("url");



const createWindow = () => {
    const newWindow = new BrowserWindow({
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

ipcMain.on("print-order", function (event, data) {
    // Start PHP server
    const phpServer = spawn(path.join(__dirname, '../php', 'php.exe'), [
        '-S',
        '127.0.0.1:8004',
        '-t',
        path.join(__dirname, '../www/')
    ]);

    phpServer.stdout.on('data', data => console.log(`PHP: ${data}`));
    phpServer.stderr.on('data', data => console.log(`PHP log: ${data}`));

    printOrder(data)
})





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


//start the app when system is ready
app.whenReady().then(() => {
    createWindow();

    //auto update process
    autoUpdater.checkForUpdatesAndNotify();

     autoUpdater.on('update-available', () => {
    dialog.showMessageBox({
      type: 'info',
      title: 'Update Available',
      message: 'A new version is downloading...'
    });
  });

  autoUpdater.on('update-downloaded', () => {
    dialog.showMessageBox({
      type: 'info',
      title: 'Update Ready',
      message: 'Update downloaded. Restart now?',
      buttons: ['Yes', 'Later']
    }).then(result => {
      if (result.response === 0) {
        autoUpdater.quitAndInstall();
      }
    });
  });

    app.on('activate', () => {
        if (BrowserWindow.getAllWindows().length === 0) createWindow()
    })
})

app.on('window-all-closed', async () => {
    await session.defaultSession.clearStorageData();
    if (process.platform !== 'darwin') app.quit();
})

