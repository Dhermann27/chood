<pre>PiOS 4b 32-bit
Refresh Rate!

sudo apt update && sudo apt install cec-utils && sudo apt install watchdog && sudo systemctl enable watchdog && sudo systemctl start watchdog && sudo apt install midori && sudo apt install xdotool

crontab
5X 5 * * * sudo reboot

echo -e "CONF_SWAPFILE=/home/chood/swapfile\nCONF_SWAPSIZE=1024" | sudo tee -a /etc/dphys-swapfile > /dev/null && sudo systemctl restart dphys-swapfile

echo -e "[Unit]\nDescription=Relaunch Browser\nAfter=multi-user.target\nWants=graphical.target\n\n[Service]\nEnvironment=\"XAUTHORITY=/home/chood/.Xauthority\"\nEnvironment=\"DISPLAY=:0\"\nExecStartPre=/bin/sleep 15\nExecStart=/bin/bash -c \"midori -e Fullscreen --display=:0 https://www.cbwcw.co/$(hostname)\"\nExecStartPost=/bin/bash -c 'sleep 15 && xdotool search --sync --onlyvisible --class midori windowactivate key Tab'\nUser=rock\nGroup=rock\nRestart=always\nRestartSec=5\nRuntimeMaxSec=3595\n[Install]\nWantedBy=multi-user.target" | sudo tee /etc/systemd/system/relaunch_browser.service > /dev/null

sudo systemctl daemon-reload && sudo systemctl enable relaunch_browser.service && touch /home/chood/.Xauthority && sudo systemctl start relaunch_browser.service

sudo reboot
</pre>
