package com.smartimmo.ideenbox

import android.app.Application

class App : Application() {
    override fun onCreate() {
        super.onCreate()
        Reminders.createChannel(this)
    }
}
