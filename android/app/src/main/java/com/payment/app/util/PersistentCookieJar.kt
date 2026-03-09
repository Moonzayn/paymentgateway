package com.payment.app.util

import android.content.Context
import okhttp3.Cookie
import okhttp3.CookieJar
import okhttp3.HttpUrl
import java.io.File
import java.io.ObjectInputStream
import java.io.ObjectOutputStream

class PersistentCookieJar(
    private val context: Context,
    private val cookieFileName: String = "cookies"
) : CookieJar {

    private var cachedCookies: MutableList<Cookie> = mutableListOf()

    init {
        loadCookies()
    }

    override fun saveFromResponse(url: HttpUrl, cookies: List<Cookie>) {
        cachedCookies.addAll(cookies)
        saveCookies()
    }

    override fun loadForRequest(url: HttpUrl): List<Cookie> {
        return cachedCookies.filter { cookie ->
            cookie.matches(url)
        }
    }

    private fun saveCookies() {
        try {
            val file = File(context.filesDir, cookieFileName)
            ObjectOutputStream(file.outputStream()).use { oos ->
                oos.writeObject(cachedCookies.toList())
            }
        } catch (e: Exception) {
            e.printStackTrace()
        }
    }

    @Suppress("UNCHECKED_CAST")
    private fun loadCookies() {
        try {
            val file = File(context.filesDir, cookieFileName)
            if (file.exists()) {
                ObjectInputStream(file.inputStream()).use { ois ->
                    cachedCookies = (ois.readObject() as List<Cookie>).toMutableList()
                }
            }
        } catch (e: Exception) {
            e.printStackTrace()
            cachedCookies = mutableListOf()
        }
    }

    fun clearCookies() {
        cachedCookies.clear()
        saveCookies()
    }
}
